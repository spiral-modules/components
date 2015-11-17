<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Database\Drivers\SQLite\Schemas;

use Spiral\Database\Entities\Schemas\AbstractTable;

/**
 * SQLIte specific table schema, some alter operations emulated using temporary tables.
 */
class TableSchema extends AbstractTable
{
    /**
     * {@inheritdoc}
     */
    protected function loadColumns()
    {
        $tableSQL = $this->driver->query(
            "SELECT sql FROM sqlite_master WHERE type = 'table' and name = ?", [$this->getName()]
        )->fetchColumn();

        /**
         * There is not really many ways to get extra information about column in SQLite, let's parse
         * table schema. As mention, spiral SQLite schema reader will support fully only tables created
         * by spiral as we expecting every column definition be on new line.
         */
        $tableStatement = explode("\n", $tableSQL);

        $columnsQuery = $this->driver->query("PRAGMA TABLE_INFO({$this->getName(true)})");

        $primaryKeys = [];
        foreach ($columnsQuery as $column) {
            if (!empty($column['pk'])) {
                $primaryKeys[] = $column['name'];
            }

            $column['tableStatement'] = $tableStatement;
            $this->registerColumn($this->columnSchema($column['name'], $column));
        }

        $this->setPrimaryKeys($primaryKeys);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function loadIndexes()
    {
        $indexesQuery = $this->driver->query("PRAGMA index_list({$this->getName(true)})");
        foreach ($indexesQuery as $index) {
            $index = $this->registerIndex($this->indexSchema($index['name'], $index));

            if ($index->getColumns() == $this->getPrimaryKeys()) {
                $this->forgetIndex($index);
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function loadReferences()
    {
        $foreignsQuery = $this->driver->query("PRAGMA foreign_key_list({$this->getName(true)})");
        foreach ($foreignsQuery as $reference) {
            $this->registerReference($this->referenceSchema($reference['id'], $reference));
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function synchroniseSchema()
    {
        if (!$this->requiresRebuild()) {
            //Probably some index changed or table renamed
            return parent::synchroniseSchema();
        }

        $this->driver->beginTransaction();

        try {
            $this->logger()->info("Rebuilding table {table} to apply required modifications.", [
                'table' => $this->getName(true)
            ]);

            //Temporary table is required to copy data over
            $temporary = $this->createTemporary();

            //Moving data over
            $this->copyData($temporary, $this->columnsMapping(true));

            //Dropping current table
            $this->commander->dropTable($this->initial->getName());

            //Renaming temporary table (should automatically handle table renaming)
            $this->commander->renameTable($temporary->getName(), $this->getName());

            //We can create needed indexes now
            foreach ($this->getIndexes() as $index) {
                $this->commander->addIndex($this, $index);
            }
        } catch (\Exception $exception) {
            $this->driver->rollbackTransaction();
            throw $exception;
        }

        $this->driver->commitTransaction();

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function columnSchema($name, $schema = null)
    {
        return new ColumnSchema($this, $name, $schema);
    }

    /**
     * {@inheritdoc}
     */
    protected function indexSchema($name, $schema = null)
    {
        return new IndexSchema($this, $name, $schema);
    }

    /**
     * {@inheritdoc}
     */
    protected function referenceSchema($name, $schema = null)
    {
        return new ReferenceSchema($this, $name, $schema);
    }

    /**
     * Rebuild is required when columns or foreign keys are altered.
     *
     * @return bool
     */
    private function requiresRebuild()
    {
        $difference = [
            count($this->comparator->addedColumns()),
            count($this->comparator->droppedColumns()),
            count($this->comparator->alteredColumns()),
            count($this->comparator->addedForeigns()),
            count($this->comparator->droppedForeigns()),
            count($this->comparator->alteredForeigns())
        ];

        return array_sum($difference) != 0;
    }

    /**
     * Temporary table.
     *
     * @return TableSchema
     */
    private function createTemporary()
    {
        //Temporary table is required to copy data over
        $temporary = clone $this;
        $temporary->setName('spiral_temp_' . $this->getName() . '_' . uniqid());

        //We don't need any index in temporary table
        foreach ($temporary->getIndexes() as $index) {
            $temporary->forgetIndex($index);
        }

        $this->commander->createTable($temporary);

        return $temporary;
    }

    /**
     * Copy table data to another location.
     *
     * @see http://stackoverflow.com/questions/4007014/alter-column-in-sqlite
     * @param AbstractTable $temporary
     * @param array         $mapping Association between old and new columns (quoted).
     */
    private function copyData(AbstractTable $temporary, array $mapping)
    {
        $this->logger()->info(
            "Copying table data from {source} to {table} using mapping ({columns}) => ({target}).",
            [
                'source'  => $this->driver->identifier($this->initial->getName()),
                'table'   => $temporary->getName(true),
                'columns' => join(', ', $mapping),
                'target'  => join(', ', array_keys($mapping))
            ]
        );

        $query = \Spiral\interpolate(
            "INSERT INTO {table} ({target}) SELECT {columns} FROM {source}",
            [
                'source'  => $this->driver->identifier($this->initial->getName()),
                'table'   => $temporary->getName(true),
                'columns' => join(', ', $mapping),
                'target'  => join(', ', array_keys($mapping))
            ]
        );

        //Let's go
        $this->driver->statement($query);
    }

    /**
     * Get mapping between new and initial columns.
     *
     * @param bool $quoted
     * @return array
     */
    private function columnsMapping($quoted = false)
    {
        $current = $this->getColumns();
        $initial = $this->initial->getColumns();

        $mapping = [];
        foreach ($current as $name => $column) {
            if (isset($initial[$name])) {
                $mapping[$column->getName($quoted)] = $initial[$name]->getName($quoted);
            }
        }

        return $mapping;
    }
}