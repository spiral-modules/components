<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Drivers\MySQL\Schemas;

use Spiral\Database\Entities\Driver;
use Spiral\Database\Entities\Schemas\AbstractCommander;
use Spiral\Database\Entities\Schemas\AbstractTable;
use Spiral\Database\Exceptions\SchemaException;

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
    private $engine = self::ENGINE_INNODB;

    /**
     * @param Driver            $driver Parent driver.
     * @param AbstractCommander $commander
     * @param string            $name   Table name, must include table prefix.
     * @param string            $prefix Database specific table prefix.
     */
    public function __construct(Driver $driver, AbstractCommander $commander, $name, $prefix)
    {
        parent::__construct($driver, $commander, $name, $prefix);

        //Let's load table type, just for fun
        if ($this->exists()) {
            $query = $driver->query('SHOW TABLE STATUS WHERE Name = ?', [$name]);
            $this->engine = $query->fetch()['Engine'];
        }
    }

    /**
     * Change table engine. Such operation will be applied only at moment of table creation.
     *
     * @param string $engine
     *
     * @return $this
     */
    public function setEngine($engine)
    {
        if ($this->exists()) {
            throw new SchemaException('Table engine can be set only at moment of creation.');
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
    protected function loadColumns()
    {
        $query = "SHOW FULL COLUMNS FROM {$this->getName(true)}";

        foreach ($this->driver->query($query)->bind(0, $name) as $column) {
            $this->registerColumn($this->columnSchema($name, $column));
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function loadIndexes()
    {
        $query = "SHOW INDEXES FROM {$this->getName(true)}";

        $indexes = [];
        $primaryKeys = [];
        foreach ($this->driver->query($query) as $index) {
            if ($index['Key_name'] == 'PRIMARY') {
                $primaryKeys[] = $index['Column_name'];
                continue;
            }

            $indexes[$index['Key_name']][] = $index;
        }

        $this->setPrimaryKeys($primaryKeys);

        foreach ($indexes as $name => $schema) {
            $this->registerIndex($this->indexSchema($name, $schema));
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function loadReferences()
    {
        $query = 'SELECT * FROM information_schema.referential_constraints '
            . 'WHERE constraint_schema = ? AND table_name = ?';

        $references = $this->driver->query($query, [$this->driver->getSource(), $this->getName()]);

        foreach ($references->all() as $reference) {
            $query = 'SELECT * FROM information_schema.key_column_usage '
                . 'WHERE constraint_name = ? AND table_schema = ? AND table_name = ?';

            $column = $this->driver->query(
                $query,
                [$reference['CONSTRAINT_NAME'], $this->driver->getSource(), $this->getName()]
            )->fetch();

            $this->registerReference(
                $this->referenceSchema($reference['CONSTRAINT_NAME'], $reference + $column)
            );
        }

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
}
