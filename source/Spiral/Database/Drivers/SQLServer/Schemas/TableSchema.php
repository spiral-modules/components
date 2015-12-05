<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Database\Drivers\SQLServer\Schemas;

use Spiral\Database\Entities\Schemas\AbstractTable;

/**
 * SQLServer table schema.
 */
class TableSchema extends AbstractTable
{
    /**
     * {@inheritdoc}
     */
    protected function loadColumns()
    {
        $query = 'SELECT * FROM information_schema.columns INNER JOIN sys.columns AS sysColumns '
            . 'ON (object_name(object_id) = table_name AND sysColumns.name = COLUMN_NAME) '
            . 'WHERE table_name = ?';

        foreach ($this->driver->query($query, [$this->getName()]) as $column) {
            $this->registerColumn($this->columnSchema($column['COLUMN_NAME'], $column));
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @link http://stackoverflow.com/questions/765867/list-of-all-index-index-columns-in-sql-server-db
     */
    protected function loadIndexes()
    {
        $query = "SELECT indexes.name AS indexName, cl.name AS columnName, "
            . "is_primary_key AS isPrimary, is_unique AS isUnique\n"
            . "FROM sys.indexes AS indexes\n"
            . "INNER JOIN sys.index_columns as columns\n"
            . "  ON indexes.object_id = columns.object_id AND indexes.index_id = columns.index_id\n"
            . "INNER JOIN sys.columns AS cl\n"
            . "  ON columns.object_id = cl.object_id AND columns.column_id = cl.column_id\n"
            . "INNER JOIN sys.tables AS t\n"
            . "  ON indexes.object_id = t.object_id\n"
            . "WHERE t.name = ? ORDER BY indexes.name, indexes.index_id, columns.index_column_id";

        $indexes = [];
        $primaryKeys = [];
        foreach ($this->driver->query($query, [$this->getName()]) as $index) {
            if ($index['isPrimary']) {
                $primaryKeys[] = $index['columnName'];
                continue;
            }

            $indexes[$index['indexName']][] = $index;
        }

        $this->setPrimaryKeys($primaryKeys);
        foreach ($indexes as $index => $schema) {
            $this->registerIndex($this->indexSchema($index, $schema));
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function loadReferences()
    {
        $references = $this->driver->query("sp_fkeys @fktable_name = ?", [$this->getName()]);
        foreach ($references as $reference) {
            $this->registerReference($this->referenceSchema($reference['FK_NAME'], $reference));
        }
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
}