<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Schemas;

use Spiral\Database\Schemas\Prototypes\AbstractTable;
use Spiral\ORM\Exceptions\SchemaException;

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
     * Get list of declared fields associated with type.
     *
     * @return array
     */
    public function getFields(): array;

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
     *
     * @return array
     *
     * @throws SchemaException
     */
    public function packSchema(SchemaBuilder $builder): array;
}