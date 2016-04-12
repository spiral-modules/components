<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Drivers\Postgres\Schemas;

use Spiral\Database\Entities\Schemas\AbstractColumn;
use Spiral\Database\Entities\Schemas\AbstractCommander;
use Spiral\Database\Entities\Schemas\AbstractTable;
use Spiral\Database\Exceptions\SchemaException;

class Commander extends AbstractCommander
{
    /**
     * {@inheritdoc}
     */
    public function alterColumn(
        AbstractTable $table,
        AbstractColumn $initial,
        AbstractColumn $column
    ) {
        if (!$initial instanceof ColumnSchema || !$column instanceof ColumnSchema) {
            throw new SchemaException('Postgres commander can work only with Postgres columns');
        }

        //Rename is separate operation
        if ($column->getName() != $initial->getName()) {
            $this->renameColumn($table, $initial, $column);

            //This call is required to correctly built set of alter operations
            $initial->setName($column->getName());
        }

        //Postgres columns should be altered using set of operations
        if (!$operations = $column->alteringOperations($initial)) {
            return $this;
        }

        //Postgres columns should be altered using set of operations
        $query = \Spiral\interpolate('ALTER TABLE {table} {operations}', [
            'table'      => $table->getName(true),
            'operations' => trim(implode(', ', $operations), ', '),
        ]);

        $this->run($query);

        return $this;
    }

    /**
     * @param AbstractTable $table
     * @param ColumnSchema  $initial
     * @param ColumnSchema  $column
     */
    private function renameColumn(AbstractTable $table, ColumnSchema $initial, ColumnSchema $column)
    {
        $statement = \Spiral\interpolate('ALTER TABLE {table} RENAME COLUMN {column} TO {name}', [
            'table'  => $table->getName(true),
            'column' => $initial->getName(true),
            'name'   => $column->getName(true),
        ]);

        $this->run($statement);
    }
}
