<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Schemas;

/**
 * Defines behaviour for a relation schemas.
 */
interface RelationInterface
{
    /**
     * Get relation name. Provided from outside?
     *
     * @return string
     */
    public function getName(): string;

    public function defineRelation(SchemaInterface $source, SchemaInterface $target);

    /**
     * List of tables required for relation to be defined besides tables related to source and
     * target record schemas. This method can be used to request map tables.
     *
     * @return array
     */
    public function mapTables(): array;

    //????????
}