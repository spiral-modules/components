<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Database\Drivers\MySQL\Schemas;

use Spiral\Database\Exceptions\SchemaException;
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
        $query = "SHOW INDEXES FROM {$this->driver->identifier($this->getName())}";

        $primaryKeys = [];
        foreach ($this->driver->query($query) as $index) {
            if ($index['Key_name'] == 'PRIMARY') {
                $primaryKeys[] = $index['Column_name'];
            }
        }

        return $primaryKeys;
    }
}