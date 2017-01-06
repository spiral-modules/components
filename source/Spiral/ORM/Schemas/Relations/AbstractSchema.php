<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Schemas\Relations;

use Spiral\ORM\Schemas\Definitions\RelationDefinition;
use Spiral\ORM\Schemas\RelationInterface;

abstract class AbstractSchema implements RelationInterface
{
    /**
     * @var RelationDefinition
     */
    protected $definition;

    /**
     * @param RelationDefinition $definition
     */
    public function __construct(RelationDefinition $definition)
    {
        $this->definition = $definition;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefinition(): RelationDefinition
    {
        return $this->definition;
    }

    public function packRelation(): array
    {
        //todo: replace
        return [];
    }
}