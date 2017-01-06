<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Schemas;

use Spiral\ORM\Schemas\Definitions\RelationDefinition;

/**
 * Defines behaviour for a relation schemas. Relation schema constructor must accept relation
 * definition as input.
 */
interface RelationInterface
{
    /**
     * Get associated relation definition.
     *
     * @return RelationDefinition
     */
    public function getDefinition(): RelationDefinition;

//    /**
//     * List of tables required for relation to be defined besides tables related to source and
//     * target record schemas. This method can be used to request map tables.
//     *
//     * @return array
//     */
//    public function mapTables(): array;

    //????????
}