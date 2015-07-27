<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Drivers\Postgres;

use Spiral\Database\Schemas\AbstractReference;

class ReferenceSchema extends AbstractReference
{
    /**
     * Parse schema information provided by parent TableSchema and populate foreign key values.
     *
     * @param mixed $schema Foreign key information fetched from database by TableSchema. Format depends
     *                      on database type.
     * @return mixed
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