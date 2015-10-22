<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)

 */
namespace Spiral\Database\Drivers\SQLite\Schemas;

use Spiral\Database\Entities\Schemas\AbstractColumn;
use Spiral\Database\Entities\Schemas\AbstractReference;
use Spiral\Database\Entities\Schemas\AbstractTable;
use Spiral\Database\Exceptions\SchemaException;

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
            "SELECT sql FROM sqlite_master WHERE type = 'table' and name = ?",
            [$this->name]
        )->fetchColumn();

        /**
         * There is not really many ways to get extra information about column in SQLite, let's parse
         * table schema. As mention, spiral SQLite schema reader will support fully only tables created
         * by spiral as we expecting every column definition be on new line.
         */
        $tableStatement = explode("\n", $tableSQL);

        foreach ($this->driver->query("PRAGMA TABLE_INFO({$this->getName(true)})") as $column) {
            if (!empty($column['pk'])) {
                $this->primaryKeys[] = $column['name'];
                $this->dbPrimaryKeys[] = $column['name'];
            }

            $column['tableStatement'] = $tableStatement;
            $this->registerColumn($column['name'], $column);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function loadIndexes()
    {
        foreach ($this->driver->query("PRAGMA index_list({$this->getName(true)})") as $index) {
            $index = $this->registerIndex($index['name'], $index);
            if ($index->getColumns() == $this->primaryKeys) {
                unset($this->indexes[$index->getName()], $this->dbIndexes[$index->getName()]);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function loadReferences()
    {
        foreach ($this->driver->query("PRAGMA foreign_key_list({$this->getName(true)})") as
                 $reference) {
            $this->registerReference($reference['id'], $reference);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function updateSchema()
    {
        if ($this->primaryKeys != $this->dbPrimaryKeys) {
            throw new SchemaException(
                "Primary keys can not be changed for already exists table ({$this->getName()})."
            );
        }

        $this->driver->beginTransaction();

        try {
            $rebuildRequired = false;
            if ($this->alteredColumns() || $this->alteredReferences()) {
                $rebuildRequired = true;
            }

            if (!$rebuildRequired) {
                foreach ($this->alteredIndexes() as $name => $schema) {
                    $dbIndex = isset($this->dbIndexes[$name]) ? $this->dbIndexes[$name] : null;

                    if (!$schema) {
                        $this->logger()->info(
                            "Dropping index [{statement}] from table {table}.", [
                            'statement' => $dbIndex->sqlStatement(true),
                            'table'     => $this->getName(true)
                        ]);

                        $this->doIndexDrop($dbIndex);
                        continue;
                    }

                    if (!$dbIndex) {
                        $this->logger()->info(
                            "Adding index [{statement}] into table {table}.", [
                            'statement' => $schema->sqlStatement(false),
                            'table'     => $this->getName(true)
                        ]);

                        $this->doIndexAdd($schema);
                        continue;
                    }

                    //Altering
                    $this->logger()->info(
                        "Altering index [{statement}] to [{new}] in table {table}.", [
                        'statement' => $dbIndex->sqlStatement(false),
                        'new'       => $schema->sqlStatement(false),
                        'table'     => $this->getName(true)
                    ]);

                    $this->doIndexChange($schema, $dbIndex);
                }
            } else {
                $this->logger()->info(
                    "Rebuilding table {table} to apply required modifications.", [
                    'table' => $this->getName(true)
                ]);

                //To be renamed later
                $tableName = $this->name;

                $this->name = 'spiral_temp_' . $this->name . '_' . uniqid();

                //SQLite index names are global
                $indexes = $this->indexes;
                $this->indexes = [];

                //Creating temporary table
                $this->createSchema();

                //Mapping columns
                $mapping = [];
                foreach ($this->columns as $name => $schema) {
                    if (isset($this->dbColumns[$name])) {
                        $mapping[$schema->getName(true)] = $this->dbColumns[$name]->getName(true);
                    }
                }

                $this->logger()->info(
                    "Migrating table data from {source} to {table} with columns mappings ({columns}) => ({target}).",
                    [
                        'source'  => $this->driver->identifier($tableName),
                        'table'   => $this->getName(true),
                        'columns' => join(', ', $mapping),
                        'target'  => join(', ', array_keys($mapping))
                    ]
                );

                //http://stackoverflow.com/questions/4007014/alter-column-in-sqlite
                $query = \Spiral\interpolate(
                    "INSERT INTO {table} ({target}) SELECT {columns} FROM {source}",
                    [
                        'source'  => $this->driver->identifier($tableName),
                        'table'   => $this->getName(true),
                        'columns' => join(', ', $mapping),
                        'target'  => join(', ', array_keys($mapping))
                    ]
                );

                $this->driver->statement($query);

                //Dropping original table
                $this->driver->statement(
                    'DROP TABLE ' . $this->driver->identifier($tableName)
                );

                //Renaming (without prefix)
                $this->rename(substr($tableName, strlen($this->tablePrefix)));

                //Restoring indexes, we can create them now
                $this->indexes = $indexes;
                foreach ($this->indexes as $index) {
                    $this->doIndexAdd($index);
                }
            }
        } catch (\Exception $exception) {
            $this->driver->rollbackTransaction();
            throw $exception;
        }

        $this->driver->commitTransaction();
    }


    /**
     * {@inheritdoc}
     */
    protected function doColumnAdd(AbstractColumn $column)
    {
        //Not supported
    }

    /**
     * {@inheritdoc}
     */
    protected function doColumnDrop(AbstractColumn $column)
    {
        //Not supported
    }

    /**
     * {@inheritdoc}
     */
    protected function doColumnChange(AbstractColumn $column, AbstractColumn $dbColumn)
    {
        //Not supported
    }

    /**
     * {@inheritdoc}
     */
    protected function doForeignAdd(AbstractReference $foreign)
    {
        //Not supported
    }

    /**
     * {@inheritdoc}
     */
    protected function doForeignDrop(AbstractReference $foreign)
    {
        //Not supported
    }

    /**
     * {@inheritdoc}
     */
    protected function doForeignChange(AbstractReference $foreign, AbstractReference $dbForeign)
    {
        //Not supported
    }
}