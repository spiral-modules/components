<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Drivers\SQLite\Schemas;

use Spiral\Database\Entities\Driver;
use Spiral\Database\Schemas\Prototypes\AbstractReference;

class SQLiteReference extends AbstractReference
{
    /**
     * {@inheritdoc}
     */
    public function sqlStatement(Driver $driver): string
    {
        $statement = [];

        $statement[] = 'FOREIGN KEY';
        $statement[] = '(' . $driver->identifier($this->column) . ')';

        $statement[] = 'REFERENCES ' . $driver->identifier($this->foreignTable);
        $statement[] = '(' . $driver->identifier($this->foreignKey) . ')';

        $statement[] = "ON DELETE {$this->deleteRule}";
        $statement[] = "ON UPDATE {$this->updateRule}";

        return implode(' ', $statement);
    }

    /**
     * @param string $table
     * @param string $tablePrefix
     * @param array  $schema
     *
     * @return SQLiteReference
     */
    public static function createInstance(string $table, string $tablePrefix, array $schema): self
    {
        $reference = new self($table, $tablePrefix, $schema['id']);

        $reference->column = $schema['from'];

        $reference->foreignTable = $schema['table'];
        $reference->foreignKey = $schema['to'];

        $reference->deleteRule = $schema['on_delete'];
        $reference->updateRule = $schema['on_update'];

        return $reference;
    }
}
