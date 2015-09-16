<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM\Entities\Relations;

use Spiral\Database\Entities\Table;
use Spiral\ORM\Entities\RecordIterator;
use Spiral\ORM\Exceptions\RelationException;
use Spiral\ORM\ORM;
use Spiral\ORM\Record;
use Spiral\ORM\RelationInterface;

/**
 * ManyToMorphed relation used to aggregate multiple ManyToMany relations based on their role type.
 * In addition it can route some function to specified nested ManyToMany relation based on record
 * role.
 *
 * @see ManyToMany
 */
class ManyToMorphed implements RelationInterface
{
    /**
     * Nested ManyToMany relations.
     *
     * @var ManyToMany[]
     */
    private $relations = [];

    /**
     * Parent Record caused relation to be created.
     *
     * @var Record
     */
    protected $parent = null;

    /**
     * Relation definition fetched from ORM schema. Must already be normalized by RelationSchema.
     *
     * @invisible
     * @var array
     */
    protected $definition = [];

    /**
     * @invisible
     * @var ORM
     */
    protected $orm = null;

    /**
     * {@inheritdoc}
     */
    public function __construct(
        ORM $orm,
        Record $parent,
        array $definition,
        $data = null,
        $loaded = false
    ) {
        $this->orm = $orm;
        $this->parent = $parent;
        $this->definition = $definition;
    }

    /**
     * {@inheritdoc}
     */
    public function isLoaded()
    {
        //Never loader
        return false;
    }

    /**
     * {@inheritdoc}
     *
     * We can return self:
     * $tag->tagged->users->count();
     */
    public function getRelated()
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function associate($related)
    {
        throw new RelationException("Unable to associate with morphed relation.");
    }

    /**
     * {@inheritdoc}
     */
    public function saveAssociation($validate = true)
    {
        foreach ($this->relations as $relation) {
            if (!$relation->saveAssociation($validate)) {
                return false;
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function reset(array $data = [], $loaded = false)
    {
        foreach ($this->relations as $relation) {
            //Can be only flushed
            $relation->reset([], false);
        }

        //Dropping relations
        $this->relations = [];
    }

    /**
     * {@inheritdoc}
     */
    public function isValid()
    {
        foreach ($this->relations as $alias => $relation) {
            if (!$relation->isValid()) {
                return false;
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function hasErrors()
    {
        foreach ($this->relations as $alias => $relation) {
            if ($relation->hasErrors()) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getErrors($reset = false)
    {
        $result = [];
        foreach ($this->relations as $alias => $relation) {
            if (!empty($errors = $relation->getErrors())) {
                $result[$alias] = $errors;
            }
        }

        return $result;
    }

    /**
     * Count method will work with pivot table directly.
     *
     * @return int
     */
    public function count()
    {
        $innerKey = $this->definition[Record::INNER_KEY];

        return $this->pivotTable()->where([
            $this->definition[Record::THOUGHT_INNER_KEY] => $this->parent->getField($innerKey)
        ])->count();
    }

    /**
     * Get access to data instance stored in nested relation.
     *
     * Example:
     * $tag->tagged->users;
     * $tag->tagged->posts;
     *
     * @param string $alias
     * @return Record|RecordIterator
     */
    public function __get($alias)
    {
        return $this->morphed($alias)->getRelated();
    }

    /**
     * Get access to sub relation.
     *
     * Example:
     * $tag->tagged->users()->count();
     * foreach($tag->tagged->users()->find(["status" => "active"]) as $user)
     * {
     * }
     *
     * @param string $alias
     * @param array  $arguments
     * @return ManyToMany
     */
    public function __call($alias, array $arguments)
    {
        return $this->morphed($alias);
    }

    /**
     * Link morphed record to relation. Method will bypass request to appropriate nested relation.
     *
     * @param Record $record
     * @param array  $pivotData Custom pivot data.
     */
    public function link(Record $record, array $pivotData = [])
    {
        $this->morphed($record->recordRole())->link($record, $pivotData);
    }

    /**
     * Unlink morphed record from relation.
     *
     * @param Record $record
     * @return int
     */
    public function unlink(Record $record)
    {
        return $this->morphed($record->recordRole())->unlink($record);
    }

    /**
     * Unlink every associated record, method will return amount of affected rows. Method will
     * unlink only records matched WHERE_PIVOT by default. Set wherePivot to false to unlink every
     * record.
     *
     * @param bool $wherePivot Use conditions specified by WHERE_PIVOT, enabled by default.
     * @return int
     */
    public function unlinkAll($wherePivot = true)
    {
        $innerKey = $this->definition[Record::INNER_KEY];

        $query = [
            $this->definition[Record::THOUGHT_INNER_KEY] => $this->parent->getField($innerKey)
        ];

        if ($wherePivot && !empty($this->definition[Record::WHERE_PIVOT])) {
            $query = $query + $this->definition[Record::WHERE_PIVOT];
        }

        return $this->pivotTable()->delete($query)->run();
    }

    /**
     * Get nested-relation associated with one of record morph aliases.
     *
     * @param string $alias
     * @return ManyToMany
     */
    protected function morphed($alias)
    {
        if (isset($this->relations[$alias])) {
            //Already loaded
            return $this->relations[$alias];
        }

        if (
            !isset($this->definition[Record::MORPHED_ALIASES][$alias])
            && !empty($reversed = array_search($alias, $this->definition[Record::MORPHED_ALIASES]))
        ) {
            //Requested by singular form of role name, let's reverse mapping
            $alias = $reversed;
        }

        if (!isset($this->definition[Record::MORPHED_ALIASES][$alias])) {
            throw new RelationException("No such morphed-relation or method '{$alias}'.");
        }

        //We have to create custom definition
        $definition = $this->definition;

        $roleName = $this->definition[Record::MORPHED_ALIASES][$alias];
        $definition[Record::MANY_TO_MANY] = $definition[Record::MANY_TO_MORPHED][$roleName];

        unset($definition[Record::MANY_TO_MORPHED], $definition[Record::MORPHED_ALIASES]);

        //Creating many-to-many relation
        $this->relations[$alias] = new ManyToMany($this->orm, $this->parent, $definition);

        //We have to force role name
        $this->relations[$alias]->setRole($roleName);

        return $this->relations[$alias];
    }

    /**
     * Instance of DBAL\Table associated with relation pivot table.
     *
     * @return Table
     */
    protected function pivotTable()
    {
        return $this->orm->dbalDatabase($this->definition[ORM::R_DATABASE])->table(
            $this->definition[Record::PIVOT_TABLE]
        );
    }
}