<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Schemas;

use Spiral\Core\FactoryInterface;
use Spiral\ORM\Configs\RelationsConfig;
use Spiral\ORM\Exceptions\DefinitionException;
use Spiral\ORM\Schemas\Definitions\RelationDefinition;

/**
 * Subsection of SchemaBuilder used to configure tables and columns defined by model to model
 * relations.
 */
class RelationManager
{
    /**
     * @invisible
     * @var RelationsConfig
     */
    protected $config;

    /**
     * @invisible
     * @var FactoryInterface
     */
    protected $factory;

    /**
     * Set of relation definitions.
     *
     * @var RelationInterface[]
     */
    private $relations = [];

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
     * @param RelationDefinition $definition Relation options (definition).
     *
     * @throws DefinitionException
     */
    public function registerRelation(RelationDefinition $definition)
    {
        if (!$this->config->hasRelation($definition->getType())) {
            throw new DefinitionException(sprintf(
                "Undefined relation type '%s' in '%s'.'%s'",
                $definition->getType(),
                $definition->sourceContext()->getClass(),
                $definition->getName()
            ));
        }

        $class = $this->config->relationClass(
            $definition->getType(),
            RelationsConfig::SCHEMA_CLASS
        );

        //Creating relation schema
        $relation = $this->factory->make($class, compact('definition'));

        //Equavalent (low)?

        //todo: make sure no dubs

        $this->relations[] = $relation;
    }

    /**
     * Create inverse relations where needed.
     *
     * @throws DefinitionException
     */
    public function inverseRelations()
    {
        /**
         * Inverse process is relation specific.
         */
        foreach ($this->relations as $relation) {
            $definition = $relation->getDefinition();

            if ($definition->needInversion()) {
                if (!$relation instanceof InversableRelationInterface) {
                    throw new DefinitionException(sprintf(
                        "Unable to inverse relation '%s'.'%s', relation schema '%s' non inversable",
                        $definition->sourceContext()->getClass(),
                        $definition->getName(),
                        get_class($relation)
                    ));
                }

                //todo: make sure no dubs

                //Let's perform inversion
                $this->registerRelation($relation->inverseDefinition($definition->getInverse()));
            }
        }
    }

    //todo: normalize relations?

    /**
     * Declare set of tables for each relation. Method must return Generator of AbstractTable
     * sequentially (attention, non sequential processing will cause collision issues between
     * tables).
     *
     * @param SchemaBuilder $builder
     *
     * @return \Generator
     */
    public function declareTables(SchemaBuilder $builder): \Generator
    {
        foreach ($this->relations as $relation) {
            foreach ($relation->declareTables($builder) as $table) {
                yield $table;
            }
        }
    }

    /**
     * Pack relation schemas for specific model class in order to be saved in memory.
     *
     * @param string $class
     *
     * @return array
     */
    public function packRelations(string $class): array
    {
        $result = [];
        foreach ($this->relations as $relation) {
            $definition = $relation->getDefinition();

            if ($definition->sourceContext()->getClass() == $class) {
                $result[$definition->getName()] = $relation->packRelation();
            }
        }

        return $result;
    }
}