<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Drivers\Postgres\Schemas;

use Spiral\Database\Entities\Schemas\AbstractReference;

/**
 * Postgres foreign key schema.
 */
class ReferenceSchema extends AbstractReference
{
    /**
     * {@inheritdoc}
     */
    protected function resolveSchema($schema)
    {
        $this->column = $schema['column_name'];

        $this->foreignTable = $schema['foreign_table_name'];
        $this->foreignKey = $schema['foreign_column_name'];

        $this->deleteRule = $schema['delete_rule'];
        $this->updateRule = $schema['update_rule'];
    }
}