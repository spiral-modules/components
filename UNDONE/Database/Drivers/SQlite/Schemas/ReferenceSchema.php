<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Drivers\Sqlite;

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
        $this->column = $schema['from'];

        $this->foreignTable = $schema['table'];
        $this->foreignKey = $schema['to'];

        $this->deleteRule = $schema['on_delete'];
        $this->updateRule = $schema['on_update'];
    }

    /**
     * Get foreign key definition statement. SQLite has reduced syntax.
     *
     * @return string
     */
    public function sqlStatement()
    {
        $statement = [];

        $statement[] = 'FOREIGN KEY';
        $statement[] = '(' . $this->table->driver()->identifier($this->column) . ')';

        $statement[] = 'REFERENCES ' . $this->table->driver()->identifier($this->foreignTable);
        $statement[] = '(' . $this->table->driver()->identifier($this->foreignKey) . ')';

        $statement[] = "ON DELETE {$this->deleteRule}";
        $statement[] = "ON UPDATE {$this->updateRule}";

        return join(' ', $statement);
    }
}