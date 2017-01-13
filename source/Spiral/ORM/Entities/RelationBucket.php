<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Entities;

use Spiral\ORM\CommandInterface;
use Spiral\ORM\Commands\CommandQueue;
use Spiral\ORM\Exceptions\RelationException;
use Spiral\ORM\ORMInterface;
use Spiral\ORM\RecordInterface;
use Spiral\ORM\RelationInterface;

/**
 * Represent set of entity relations.
 */
class RelationBucket
{
    /**
     * @var array|RelationInterface[]
     */
    private $relations = [];

    /**
     * Parent class name.
     *
     * @var string
     */
    private $class;

    /**
     * Relations schema.
     *
     * @var array
     */
    private $schema = [];

    /**
     * Associates ORM manager.
     *
     * @var ORMInterface
     */
    protected $orm;

    /**
     * @param RecordInterface $record
     * @param ORMInterface    $orm
     */
    public function __construct(RecordInterface $record, ORMInterface $orm)
    {
        $this->class = get_class($record);
        $this->schema = $orm->define($this->class, ORMInterface::R_RELATIONS);
        $this->orm = $orm;
    }

    /**
     * Extract relations data from given entity fields.
     *
     * @param array $data
     */
    public function extractRelations(array &$data)
    {
        //Fetch all relations
        $relations = array_intersect_key($data, $this->schema);

        foreach ($relations as $name => $relation) {
            $this->relations[$name] = $relation;
            unset($data[$name]);
        }
    }

    /**
     * Generate command tree with or without relation to parent command in order to specify update
     * or insert sequence. Commands might define dependencies between each other in order to extend
     * FK values.
     *
     * @param CommandInterface $parent
     *
     * @return CommandInterface
     */
    public function queueRelations(CommandInterface $parent): CommandInterface
    {
        if (empty($this->relations)) {
            //No relations exists, nothing to do
            return $parent;
        }

        $queue = new CommandQueue();

        //Leading relations
        foreach ($this->leadingRelations() as $relation) {
            //Generating commands needed to save given relation prior to parent command
            $queue->addCommand($relation->queueCommands($parent));
        }

        //Parent model save operations
        $queue->addCommand($parent);

        //Depended relations
        foreach ($this->dependedRelations() as $relation) {
            //Generating commands needed to save relations after parent command being executed
            $queue->addCommand($relation->queueCommands($parent));
        }

        return $queue;
    }

    /**
     * Check if parent entity has associated relation.
     *
     * @param string $relation
     *
     * @return bool
     */
    public function has(string $relation): bool
    {
        return isset($this->schema[$relation]);
    }

    /**
     * Check if relation has any associated data with it (attention, non loaded relation will be
     * automatically pre-loaded).
     *
     * @param string $relation
     *
     * @return bool
     */
    public function hasRelated(string $relation): bool
    {
        return $this->get($relation)->hasRelated();
    }

    /**
     * Data data which is being associated with relation, relation is allowed to return itself if
     * needed.
     *
     * @param string $relation
     *
     * @return RelationInterface|RecordInterface|mixed
     *
     * @throws RelationException
     */
    public function getRelated(string $relation)
    {
        return $this->get($relation)->getRelated();
    }

    /**
     * Associated relation with new value (must be compatible with relation format).
     *
     * @param string $relation
     * @param mixed  $value
     *
     * @throws RelationException
     */
    public function setRelated(string $relation, $value)
    {
        if (is_null($value)) {
            $this->flushRelated($relation);
        }

        $this->get($relation)->setRelated($value);
    }

    /**
     * De-associated relation data (idential to assign to null).
     *
     * @param string $relation
     */
    public function flushRelated(string $relation)
    {
        $this->get($relation)->flushRelated();
    }

    /**
     * Information about loaded relations.
     *
     * @return array
     */
    public function __debugInfo()
    {
        $relations = [];

        foreach ($this->schema as $name => $content) {
            if (!array_key_exists($name, $this->relations)) {
                $relations[$name] = 'none';
                continue;
            }

            $relations[$name] = empty($this->relations[$name]) ? 'empty' : 'loaded';
        }

        return $relations;
    }

    /**
     * Get associated relation instance.
     *
     * @param string $relation
     *
     * @return RelationInterface
     */
    protected function get(string $relation): RelationInterface
    {
        if ($this->relations[$relation] instanceof RelationInterface) {
            return $this->relations[$relation];
        }

        $instance = $this->orm->makeRelation($this->class, $relation);
        if (array_key_exists($relation, $this->relations)) {
            //Relation have been pre-loaded (we have related data)
            $instance->initData($this->relations[$relation]);
        }

        return $this->relations[$relation] = $instance;
    }

    /**
     * list of relations which lead data of parent record (BELONGS_TO).
     *
     * Example:
     *
     * $post = new Post();
     * $post->user = new User();
     *
     * @return RelationInterface[]
     */
    protected function leadingRelations()
    {
        return [];
    }

    /**
     * list of loaded relations which depend on parent record (HAS_MANY, MANY_TO_MANY and etc).
     *
     * Example:
     *
     * $post = new Post();
     * $post->comments->add(new Comment());
     *
     * @return RelationInterface[]
     */
    protected function dependedRelations()
    {
        return [];
    }
}