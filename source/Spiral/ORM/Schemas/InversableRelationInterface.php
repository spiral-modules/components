<?php
/**
 * components
 *
 * @author    Wolfy-J
 */

namespace Spiral\ORM\Schemas;

use Spiral\ORM\Exceptions\DefinitionException;
use Spiral\ORM\Exceptions\RelationSchemaException;
use Spiral\ORM\Schemas\Definitions\RelationDefinition;

interface InversableRelationInterface extends RelationInterface
{
    /**
     * Get new relation(s) definition with inversed properties.
     *
     * @param SchemaBuilder $builder
     * @param string|array  $inverseTo Name of relation to be inversed to. In some cases MIGHT
     *                                 include relation type in a form [type, outer relation].
     *
     * @return RelationDefinition|RelationDefinition[]
     *
     * @throws RelationSchemaException
     * @throws DefinitionException
     */
    public function inverseDefinition(SchemaBuilder $builder, $inverseTo);
}