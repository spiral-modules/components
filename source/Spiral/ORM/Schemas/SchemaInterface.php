<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Schemas;

use Spiral\Database\Schemas\Prototypes\AbstractTable;
use Spiral\ORM\Exceptions\SchemaException;
use Spiral\ORM\Schemas\Definitions\IndexDefinition;
use Spiral\ORM\Schemas\Definitions\RelationDefinition;

interface SchemaInterface
{
    /**
     * Related class name.
     *
     * @return string
     */
    public function getClass(): string;

    /**
     * Name of class responsible for model instantiation.
     *
     * @return string
     */
    public function getInstantiator(): string;

    /**
     * Name of associated database. Might return null to force default database usage.
     *
     * @return string|null
     *
     * @throws SchemaException
     */
    public function getDatabase();

    /**
     * Name of associated table.
     *
     * @return string
     *
     * @throws SchemaException
     */
    public function getTable(): string;

    /**
     * Get indexes declared by model.
     *
     * @return IndexDefinition[]
     *
     * @throws SchemaException
     */
    public function getIndexes();

    /**
     * Get all defined record relations.
     *
     * @return RelationDefinition[]
     *
     * @throws SchemaException
     */
    public function getRelations();

    /**
     * Define needed columns, indexes and foreign keys in a record related table.
     *
     * @param AbstractTable $table
     *
     * @return AbstractTable
     *
     * @throws SchemaException
     */
    public function defineTable(AbstractTable $table): AbstractTable;

    /**
     * Pack schema in a form compatible with entity class and selected mapper.
     *
     * @param SchemaBuilder $builder
     * @param AbstractTable $table
     *
     * @return array
     *
     * @throws SchemaException
     */
    public function packSchema(SchemaBuilder $builder, AbstractTable $table = null): array;
}