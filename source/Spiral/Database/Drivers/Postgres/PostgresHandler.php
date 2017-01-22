<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\Database\Drivers\Postgres;

use Spiral\Database\Drivers\Postgres\Schemas\PostgresColumn;
use Spiral\Database\Entities\AbstractHandler;
use Spiral\Database\Exceptions\SchemaException;
use Spiral\Database\Schemas\Prototypes\AbstractColumn;
use Spiral\Database\Schemas\Prototypes\AbstractTable;

class PostgresHandler extends AbstractHandler
{
    /**
     * {@inheritdoc}
     *
     * @throws SchemaException
     */
    public function alterColumn(
        AbstractTable $table,
        AbstractColumn $initial,
        AbstractColumn $column
    ) {
        if (!$initial instanceof PostgresColumn || !$column instanceof PostgresColumn) {
            throw new SchemaException('Postgres handler can work only with Postgres columns');
        }

        //Rename is separate operation
        if ($column->getName() != $initial->getName()) {
            $this->renameColumn($table, $initial, $column);

            //This call is required to correctly built set of alter operations
            $initial->setName($column->getName());
        }

        //Postgres columns should be altered using set of operations
        $operations = $column->alterOperations($this->driver, $initial);
        if (empty($operations)) {
            return;
        }

        //Postgres columns should be altered using set of operations
        $query = \Spiral\interpolate('ALTER TABLE {table} {operations}', [
            'table'      => $this->identify($table),
            'operations' => trim(implode(', ', $operations), ', '),
        ]);

        $this->run($query);
    }

    /**
     * @param AbstractTable  $table
     * @param AbstractColumn $initial
     * @param AbstractColumn $column
     */
    private function renameColumn(
        AbstractTable $table,
        AbstractColumn $initial,
        AbstractColumn $column
    ) {
        $statement = \Spiral\interpolate('ALTER TABLE {table} RENAME COLUMN {column} TO {name}', [
            'table'  => $this->identify($table),
            'column' => $this->identify($initial),
            'name'   => $this->identify($column)
        ]);

        $this->run($statement);
    }
}