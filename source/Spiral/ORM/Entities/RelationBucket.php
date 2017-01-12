<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Entities;

use Spiral\ORM\CommandInterface;
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
        $this->orm = $orm;
        $this->schema = $orm->define(get_class($record), ORMInterface::R_RELATIONS);
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

    public function queueRelations(CommandInterface $parent): CommandInterface
    {

        return $parent;
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
     * Get associated relation instance.
     *
     * @param string $relation
     *
     * @return RelationInterface
     */
    public function get(string $relation): RelationInterface
    {

    }

    public function set(string $relation, $value)
    {

    }

    public function flush(string $relation)
    {

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
}