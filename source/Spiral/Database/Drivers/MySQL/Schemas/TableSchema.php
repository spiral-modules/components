<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Drivers\MySql;

use Spiral\Database\Entities\Schemas\AbstractColumn;
use Spiral\Database\Entities\Schemas\AbstractIndex;
use Spiral\Database\Entities\Schemas\AbstractReference;
use Spiral\Database\Entities\Schemas\AbstractTable;

/**
 * MySQL table schema.
 */
class TableSchema extends AbstractTable
{
    /**
     * List of most common MySQL table engines.
     */
    const ENGINE_INNODB = 'InnoDB';
    const ENGINE_MYISAM = 'MyISAM';
    const ENGINE_MEMORY = 'Memory';

    /**
     * MySQL table engine.
     *
     * @var string
     */
    protected $engine = self::ENGINE_INNODB;

    /**
     * Change table engine. Such operation will be applied only at moment of table creation.
     *
     * @param string $engine
     * @return $this
     */
    public function setEngine($engine)
    {
        $this->engine = $engine;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function loadColumns()
    {
        $query = \Spiral\interpolate(
            "SHOW FULL COLUMNS FROM {table}", ['table' => $this->getName(true)]
        );

        foreach ($this->driver->query($query)->bind(1, $columnName) as $column)
        {
            $this->registerColumn($columnName, $column);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function loadIndexes()
    {
        $indexes = [];
        $query = \Spiral\interpolate("SHOW INDEXES FROM {table}", ['table' => $this->getName(true)]);
        foreach ($this->driver->query($query) as $index)
        {
            if ($index['Key_name'] == 'PRIMARY')
            {
                $this->primaryKeys[] = $index['Column_name'];
                $this->dbPrimaryKeys[] = $index['Column_name'];
                continue;
            }

            $indexes[$index['Key_name']][] = $index;
        }

        foreach ($indexes as $index => $schema)
        {
            $this->registerIndex($index, $schema);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function loadReferences()
    {
        $query = "SELECT * FROM information_schema.referential_constraints "
            . "WHERE constraint_schema = ? AND table_name = ?";

        $references = $this->driver->query($query, [$this->driver->getDatabaseName(), $this->name]);

        foreach ($references as $reference)
        {
            $query = "SELECT * FROM information_schema.key_column_usage "
                . "WHERE constraint_name = ? AND table_schema = ? AND table_name = ?";

            $column = $this->driver->query($query, [
                $reference['CONSTRAINT_NAME'],
                $this->driver->getDatabaseName(),
                $this->name
            ])->fetch();

            $this->registerReference($reference['CONSTRAINT_NAME'], $reference + $column);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function createSchema($execute = true)
    {
        $statement = parent::createSchema(false);

        //Additional table options
        $options = "ENGINE = {engine}";
        $statement = $statement . ' ' . \Spiral\interpolate($options, ['engine' => $this->engine]);

        //Executing
        $execute && $this->driver->statement($statement);

        if ($execute)
        {
            //Not all databases support adding index while table creation, so we can do it after
            foreach ($this->indexes as $index)
            {
                $this->doIndexAdd($index);
            }
        }

        return $statement;
    }

    /**
     * {@inheritdoc}
     */
    protected function doColumnChange(AbstractColumn $column, AbstractColumn $dbColumn)
    {
        $query = \Spiral\interpolate("ALTER TABLE {table} CHANGE {column} {statement}", [
            'table'     => $this->getName(true),
            'column'    => $dbColumn->getName(true),
            'statement' => $column->sqlStatement()
        ]);

        $this->driver->statement($query);
    }

    /**
     * {@inheritdoc}
     */
    protected function doIndexDrop(AbstractIndex $index)
    {
        $this->driver->statement("DROP INDEX {$index->getName(true)} ON {$this->getName(true)}");
    }

    /**
     * {@inheritdoc}
     */
    protected function doIndexChange(AbstractIndex $index, AbstractIndex $dbIndex)
    {
        $query = \Spiral\interpolate("ALTER TABLE {table} DROP INDEX {original}, ADD {statement}", [
            'table'     => $this->getName(true),
            'original'  => $dbIndex->getName(true),
            'statement' => $index->sqlStatement(false)
        ]);

        $this->driver->statement($query);
    }

    /**
     * {@inheritdoc}
     */
    protected function doForeignDrop(AbstractReference $foreign)
    {
        $this->driver->statement(
            "ALTER TABLE {$this->getName(true)} DROP FOREIGN KEY {$foreign->getName(true)}"
        );
    }
}