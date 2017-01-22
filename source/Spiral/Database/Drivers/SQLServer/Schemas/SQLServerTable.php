<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\Database\Drivers\SQLServer\Schemas;

use Psr\Log\LoggerInterface;
use Spiral\Database\Entities\AbstractHandler as Behaviour;
use Spiral\Database\Schemas\Prototypes\AbstractColumn;
use Spiral\Database\Schemas\Prototypes\AbstractIndex;
use Spiral\Database\Schemas\Prototypes\AbstractReference;
use Spiral\Database\Schemas\Prototypes\AbstractTable;

class SQLServerTable extends AbstractTable
{
    /**
     * {@inheritdoc}
     *
     * SQLServer will reload schemas after successful savw.
     */
    public function save(
        int $behaviour = Behaviour::DO_ALL,
        LoggerInterface $logger = null,
        bool $reset = true
    ) {
        parent::save($behaviour, $logger, $reset);

        if ($reset) {
            foreach ($this->fetchColumns() as $column) {
                $currentColumn = $this->current->findColumn($column->getName());
                if (!empty($currentColumn) && $column->compare($currentColumn)) {
                    //SQLServer is going to add some automatic constrains, let's handle them
                    $this->current->registerColumn($column);
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function fetchColumns(): array
    {
        $query = 'SELECT * FROM [information_schema].[columns] INNER JOIN [sys].[columns] AS [sysColumns] '
            . 'ON (object_name([object_id]) = [table_name] AND [sysColumns].[name] = [COLUMN_NAME]) '
            . 'WHERE [table_name] = ?';

        $result = [];
        foreach ($this->driver->query($query, [$this->getName()]) as $schema) {
            //Column initialization needs driver to properly resolve enum type
            $result[] = SQLServerColumn::createInstance(
                $this->getName(),
                $schema,
                $this->driver
            );
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function fetchIndexes(): array
    {
        $query = 'SELECT [indexes].[name] AS [indexName], [cl].[name] AS [columnName], '
            . "[is_primary_key] AS [isPrimary], [is_unique] AS [isUnique]\n"
            . "FROM [sys].[indexes] AS [indexes]\n"
            . "INNER JOIN [sys].[index_columns] as [columns]\n"
            . "  ON [indexes].[object_id] = [columns].[object_id] AND [indexes].[index_id] = [columns].[index_id]\n"
            . "INNER JOIN [sys].[columns] AS [cl]\n"
            . "  ON [columns].[object_id] = [cl].[object_id] AND [columns].[column_id] = [cl].[column_id]\n"
            . "INNER JOIN [sys].[tables] AS [t]\n"
            . "  ON [indexes].[object_id] = [t].[object_id]\n"
            . 'WHERE [t].[name] = ? AND [is_primary_key] = 0 ORDER BY [indexes].[name], [indexes].[index_id], [columns].[index_column_id]';

        $result = $indexes = [];
        foreach ($this->driver->query($query, [$this->getName()]) as $index) {
            //Collecting schemas first
            $indexes[$index['indexName']][] = $index;
        }

        foreach ($indexes as $name => $schema) {
            //Once all columns are aggregated we can finally create an index
            $result[] = SQLServerIndex::createInstance($this->getName(), $schema);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function fetchReferences(): array
    {
        $references = $this->driver->query('sp_fkeys @fktable_name = ?', [$this->getName()]);

        $result = [];
        foreach ($references as $schema) {
            $result[] = SQlServerReference::createInstance(
                $this->getName(),
                $this->getPrefix(),
                $schema
            );
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function fetchPrimaryKeys(): array
    {
        $query = "SELECT [indexes].[name] AS [indexName], [cl].[name] AS [columnName]\n"
            . "FROM [sys].[indexes] AS [indexes]\n"
            . "INNER JOIN [sys].[index_columns] as [columns]\n"
            . "  ON [indexes].[object_id] = [columns].[object_id] AND [indexes].[index_id] = [columns].[index_id]\n"
            . "INNER JOIN [sys].[columns] AS [cl]\n"
            . "  ON [columns].[object_id] = [cl].[object_id] AND [columns].[column_id] = [cl].[column_id]\n"
            . "INNER JOIN [sys].[tables] AS [t]\n"
            . "  ON [indexes].[object_id] = [t].[object_id]\n"
            . 'WHERE [t].[name] = ? AND [is_primary_key] = 1 ORDER BY [indexes].[name], [indexes].[index_id], [columns].[index_column_id]';

        $result = [];
        foreach ($this->driver->query($query, [$this->getName()]) as $schema) {
            $result[] = $schema['columnName'];
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function createColumn(string $name): AbstractColumn
    {
        return new SQLServerColumn($this->getName(), $name, $this->driver->getTimezone());
    }

    /**
     * {@inheritdoc}
     */
    protected function createIndex(string $name): AbstractIndex
    {
        return new SQLServerIndex($this->getName(), $name);
    }

    /**
     * {@inheritdoc}
     */
    protected function createForeign(string $name): AbstractReference
    {
        return new SQlServerReference($this->getName(), $this->getPrefix(), $name);
    }
}