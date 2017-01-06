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
                $definition->getSourceContext()->getClass(),
                $definition->getName()
            ));
        }

        $class = $this->config->relationClass(
            $definition->getType(),
            RelationsConfig::SCHEMA_CLASS
        );

        //Creating relation schema
        $this->relations[] = $this->factory->make($class, compact('definition'));
    }

    /**
     * Create inverse relations where needed.
     */
    public function inverseRelations()
    {

    }

    public function packRelations(string $class): array
    {
        return [];
    }
}