<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Drivers\SQLite\Schemas;

use Spiral\Database\Schemas\AbstractReference;

/**
 * SQLite foreign key schema.
 */
class ReferenceSchema extends AbstractReference
{
    /**
     * {@inheritdoc}
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

        return implode(' ', $statement);
    }

    /**
     * {@inheritdoc}
     */
    protected function resolveSchema($schema)
    {
        $this->column = $schema['from'];

        $this->foreignTable = $schema['table'];
        $this->foreignKey = $schema['to'];

        $this->deleteRule = $schema['on_delete'];
        $this->updateRule = $schema['on_update'];
    }
}
