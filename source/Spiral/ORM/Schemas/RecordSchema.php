<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Schemas;

use Spiral\Database\Schemas\Prototypes\AbstractTable;

class RecordSchema implements SchemaInterface
{
    /**
     * {@inheritdoc}
     */
    public function getClass(): string
    {
        // TODO: Implement getClass() method.
    }

    /**
     * {@inheritdoc}
     */
    public function getInstantiator(): string
    {
        // TODO: Implement getInstantiator() method.
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabase()
    {
        // TODO: Implement getDatabase() method.
    }

    /**
     * {@inheritdoc}
     */
    public function getTable(): string
    {
        // TODO: Implement getTable() method.
    }

    /**
     * {@inheritdoc}
     */
    public function getFields(): array
    {
        // TODO: Implement getFields() method.
    }

    /**
     * {@inheritdoc}
     */
    public function defineTable(AbstractTable $table): AbstractTable
    {
        // TODO: Implement defineTable() method.
    }

    /**
     * {@inheritdoc}
     */
    public function packSchema(SchemaBuilder $builder): array
    {
        // TODO: Implement packSchema() method.
    }

}