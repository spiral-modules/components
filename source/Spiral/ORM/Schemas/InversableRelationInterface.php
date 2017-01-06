<?php
/**
 * components
 *
 * @author    Wolfy-J
 */

namespace Spiral\ORM\Schemas;

use Spiral\ORM\Schemas\Definitions\RelationDefinition;

interface InversableRelationInterface extends RelationInterface
{
    /**
     * Get new relation definition with inversed properties.
     *
     * @return RelationDefinition
     */
    public function inverseDefinition(): RelationDefinition;
}