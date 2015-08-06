<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Drivers\Postgres\Schemas;

use Spiral\Database\Entities\Schemas\AbstractColumn;
use Spiral\Database\Entities\Schemas\AbstractTable;

/**
 * Postgres table schema.
 */
class TableSchema extends AbstractTable
{
    /**
     * Sequence object name usually defined only for primary keys and required by ORM to correctly
     * resolve inserted row id.
     *
     * @var string|null
     */
    private $sequenceName = null;

    /**
     * Sequence object name usually defined only for primary keys and required by ORM to correctly
     * resolve inserted row id.
     *
     * @return string
     */
    public function getSequence()
    {
        return $this->sequenceName;
    }

    /**
     * {@inheritdoc}
     */
    protected function loadColumns()
    {
        //Required for constraints fetch
        $tableOID = $this->driver->query("SELECT oid FROM pg_class WHERE relname = ?", [$this->name])
            ->fetchColumn();

        //Collecting all candidates
        $this->sequenceName = [];
        $query = "SELECT * FROM information_schema.columns "
            . "JOIN pg_type ON (pg_type.typname = columns.udt_name) "
            . "WHERE table_name = ?";

        $columns = $this->driver->query($query, [$this->name])->bind('column_name', $columnName);

        foreach ($columns as $column) {
            if (preg_match(
                '/^nextval\([\'"]([a-z0-9_"]+)[\'"](?:::regclass)?\)$/i',
                $column['column_default'],
                $matches
            )) {
                $this->sequenceName[$columnName] = $matches[1];
            }

            $this->registerColumn($columnName, $column + ['tableOID' => $tableOID]);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function loadIndexes()
    {
        $query = "SELECT * FROM pg_indexes WHERE schemaname = 'public' AND tablename = ?";
        foreach ($this->driver->query($query, [$this->name]) as $index) {
            $index = $this->registerIndex($index['indexname'], $index['indexdef']);

            $conType = $this->driver
                ->query("SELECT contype FROM pg_constraint WHERE conname = ?", [$index->getName()])
                ->fetchColumn();

            if ($conType == 'p') {
                $this->primaryKeys = $this->dbPrimaryKeys = $index->getColumns();
                unset($this->indexes[$index->getName()], $this->dbIndexes[$index->getName()]);

                if (is_array($this->sequenceName) && count($index->getColumns()) === 1) {
                    $column = $index->getColumns()[0];
                    if (isset($this->sequenceName[$column])) {
                        //We found our primary sequence
                        $this->sequenceName = $this->sequenceName[$column];
                    }
                }
            }
        }

        if (is_array($this->sequenceName)) {
            //Unable to resolve
            $this->sequenceName = null;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function loadReferences()
    {
        $query = "SELECT tc.constraint_name, tc.table_name, kcu.column_name, rc.update_rule,
                  rc.delete_rule, ccu.table_name AS foreign_table_name,
                  ccu.column_name AS foreign_column_name
                  FROM information_schema.table_constraints AS tc
                  JOIN information_schema.key_column_usage AS kcu
                      ON tc.constraint_name = kcu.constraint_name
                  JOIN information_schema.constraint_column_usage AS ccu
                      ON ccu.constraint_name = tc.constraint_name
                  JOIN information_schema.referential_constraints AS rc
                      ON rc.constraint_name = tc.constraint_name
                  WHERE constraint_type = 'FOREIGN KEY' AND tc.table_name=?";

        foreach ($this->driver->query($query, [$this->name]) as $reference) {
            $this->registerReference($reference['constraint_name'], $reference);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function doColumnChange(AbstractColumn $column, AbstractColumn $dbColumn)
    {
        /**
         * @var ColumnSchema $column
         */

        //Rename is separate operation
        if ($column->getName() != $dbColumn->getName()) {
            $this->driver->statement(\Spiral\interpolate(
                'ALTER TABLE {table} RENAME COLUMN {original} TO {column}',
                [
                    'table'    => $this->getName(true),
                    'column'   => $column->getName(true),
                    'original' => $dbColumn->getName(true)
                ]
            ));

            $column->setName($dbColumn->getName());
        }

        //Postgres columns should be altered using set of operations
        if (!$operations = $column->alterOperations($dbColumn)) {
            return;
        }

        //Postgres columns should be altered using set of operations
        $query = \Spiral\interpolate('ALTER TABLE {table} {operations}', [
            'table'      => $this->getName(true),
            'operations' => trim(join(', ', $operations), ', ')
        ]);

        $this->driver->statement($query);
    }
}