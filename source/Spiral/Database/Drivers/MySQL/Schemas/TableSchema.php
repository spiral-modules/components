<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Database\Drivers\MySQL\Schemas;

use Spiral\Database\Exceptions\SchemaException;
use Spiral\Database\Schemas\Prototypes\AbstractColumn;
use Spiral\Database\Schemas\Prototypes\AbstractIndex;
use Spiral\Database\Schemas\Prototypes\AbstractReference;
use Spiral\Database\Schemas\Prototypes\AbstractTable;
use Spiral\Database\Schemas\TableState;

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
    private $engine = self::ENGINE_INNODB;

    /**
     * Populate table schema with values from database.
     *
     * @param TableState $state
     */
    protected function initSchema(TableState $state)
    {
        parent::initSchema($state);

        //Reading table schema
        $this->engine = $this->driver->query('SHOW TABLE STATUS WHERE `Name` = ?', [
            $state->getName()
        ])->fetch()['Engine'];
    }

    /**
     * Change table engine. Such operation will be applied only at moment of table creation.
     *
     * @param string $engine
     *
     * @return $this
     *
     * @throws SchemaException
     */
    public function setEngine($engine)
    {
        if ($this->exists()) {
            throw new SchemaException('Table engine can be set only at moment of creation');
        }

        $this->engine = $engine;

        return $this;
    }

    /**
     * @return string
     */
    public function getEngine()
    {
        return $this->engine;
    }

    /**
     * {@inheritdoc}
     */
    protected function fetchColumns(): array
    {
        $query = "SHOW FULL COLUMNS FROM {$this->driver->identifier($this->getName())}";

        $result = [];
        foreach ($this->driver->query($query) as $schema) {
            $result[] = ColumnSchema::createInstance($this->getName(), $schema);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function fetchIndexes(): array
    {
        $query = "SHOW INDEXES FROM {$this->driver->identifier($this->getName())}";

        //Gluing all index definitions together
        $schemas = [];
        foreach ($this->driver->query($query) as $index) {
            if ($index['Key_name'] == 'PRIMARY') {
                //Skipping PRIMARY index
                continue;
            }

            $schemas[$index['Key_name']][] = $index;
        }

        $result = [];
        foreach ($schemas as $name => $index) {
            $result[] = IndexSchema::createInstance($this->getName(), $name, $index);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function fetchReferences(): array
    {
        $references = $this->driver->query(
            'SELECT * FROM `information_schema`.`referential_constraints` WHERE `constraint_schema` = ? AND `table_name` = ?',
            [$this->driver->getSource(), $this->getName()]
        );

        $result = [];
        foreach ($references as $schema) {
            $references = 'SELECT * FROM `information_schema`.`key_column_usage` WHERE `constraint_name` = ? AND `table_schema` = ? AND `table_name` = ?';

            $column = $this->driver->query(
                $references,
                [$schema['CONSTRAINT_NAME'], $this->driver->getSource(), $this->getName()]
            )->fetch();

            $result[] = ReferenceSchema::createInstance(
                $this->getName(),
                $this->getPrefix(),
                $schema + $column
            );
        }

        return $result;
    }

    /**
     * Fetching primary keys from table.
     *
     * @return array
     */
    protected function fetchPrimaryKeys(): array
    {
        $query = "SHOW INDEXES FROM {$this->driver->identifier($this->getName())}";

        $primaryKeys = [];
        foreach ($this->driver->query($query) as $index) {
            if ($index['Key_name'] == 'PRIMARY') {
                $primaryKeys[] = $index['Column_name'];
            }
        }

        return $primaryKeys;
    }

    /**
     * {@inheritdoc}
     */
    protected function createColumn(string $name): AbstractColumn
    {
        return new ColumnSchema($this->getName(), $name);
    }

    /**
     * {@inheritdoc}
     */
    protected function createIndex(string $name): AbstractIndex
    {
        return new IndexSchema($this->getName(), $name);
    }

    /**
     * {@inheritdoc}
     */
    protected function createReference(string $column): AbstractReference
    {
        return new ReferenceSchema($this->getName(), $this->getPrefix(), $column);
    }
}