<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Schemas;

use Spiral\Core\FactoryInterface;
use Spiral\ORM\Configs\RelationsConfig;
use Spiral\ORM\Schemas\Definitions\RelationDefinition;

/**
 * Subsection of SchemaBuilder used to configure tables and columns defined by model to model
 * relations.
 */
class RelationManager
{
    /**
     * @var RelationsConfig
     */
    protected $config;

    /**
     * @var FactoryInterface
     */
    protected $factory;

    /**
     * @param RelationsConfig  $config
     * @param FactoryInterface $factory
     */
    public function __construct(RelationsConfig $config, FactoryInterface $factory)
    {
        $this->config = $config;
        $this->factory = $factory;
    }

    /**
     * Registering new relation definition.
     *
     * @param RelationDefinition $relation Relation options (definition).
     */
    public function registerRelation(RelationDefinition $relation)
    {
        dump($relation);
    }

    /**
     * Create inverse relations where needed.
     */
    public function inverseRelations()
    {

    }
}