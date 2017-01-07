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
     * Get new relation definition with inversed properties.
     *
     * @param mixed $inverseTo Name of relation to be inversed to.
     *
     * @return RelationDefinition
     *
     * @throws RelationSchemaException
     * @throws DefinitionException
     */
    public function inverseDefinition(string $inverseTo): RelationDefinition;
}