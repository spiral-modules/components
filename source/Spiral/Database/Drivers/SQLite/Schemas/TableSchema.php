<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Database\Drivers\SQLite\Schemas;

use Spiral\Database\Schemas\Prototypes\AbstractTable;

class TableSchema extends AbstractTable
{
    /**
     * {@inheritdoc}
     */
    protected function fetchColumns(): array
    {
        /**
         * Parsing column definitions.
         */
        $definition = $this->driver->query(
            "SELECT sql FROM 'sqlite_master' WHERE type = 'table' and name = ?",
            [$this->getName()]
        )->fetchColumn();

        /*
        * There is not really many ways to get extra information about column in SQLite, let's parse
        * table schema. As mention, spiral SQLite schema reader will support fully only tables created
        * by spiral as we expecting every column definition be on new line.
        */
        $definition = explode("\n", $definition);

        $result = [];
        foreach ($this->columnSchemas(['table' => $definition]) as $schema) {
            //Making new column instance
            $result[] = ColumnSchema::createInstance(
                $this->getName(),
                $schema + [
                    'quoted'     => $this->driver->quote($schema['name']),
                    'identifier' => $this->driver->identifier($schema['name'])
                ]
            );
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function fetchIndexes(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    protected function fetchReferences(): array
    {
        return [];
    }

    /**
     * Fetching primary keys from table.
     *
     * @return array
     */
    protected function fetchPrimaryKeys(): array
    {
        $primaryKeys = [];
        foreach ($this->columnSchemas() as $column) {
            if (!empty($column['pk'])) {
                $primaryKeys[] = $column['name'];
            }
        }

        return $primaryKeys;
    }

    /**
     * @param array $include Include following parameters into each line.
     *
     * @return array
     */
    private function columnSchemas(array $include = []): array
    {
        $columns = $this->driver->query(
            "PRAGMA TABLE_INFO(" . $this->driver->quote($this->getName()) . ")"
        );

        $result = [];

        foreach ($columns as $column) {
            $result[] = $column + $include;
        }

        return $result;
    }
}