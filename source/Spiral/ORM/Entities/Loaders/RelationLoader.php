<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Entities\Loaders;

use Spiral\ORM\ORMInterface;

/**
 * Provides ability to load relation data in a form of JOIN or external query.
 */
abstract class RelationLoader extends AbstractLoader
{
    /**
     * Relation schema.
     *
     * @var array
     */
    protected $relation = [];

    /**
     * @param string       $class    Class name specific to this relation.
     * @param string       $relation Relation name (i.e. container).
     * @param array        $schema   Packed relation schema.
     * @param ORMInterface $orm
     */
    public function __construct(string $class, string $relation, array $schema, ORMInterface $orm)
    {
        parent::__construct($class, $orm);

        $relations = $orm->define($class, ORMInterface::R_RELATIONS);
    }
}