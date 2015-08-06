<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Entities\Schemas;

use Spiral\Database\Entities\Driver;
use Spiral\Database\Schemas\TableInterface;
use Spiral\Debug\Traits\LoggerTrait;

/**
 *      * Table schema instance used both for reading and writing table schema in database. TableSchema
 * provides set of abstractions used to unify database architecting across different DBMS.
 *
 *
 *
 */
abstract class AbstractTable implements TableInterface
{
    /**
     * AbstractTable will raise few warning and debug messages to console.
     */
    use LoggerTrait;

    /**
     * Rename SQL statement is usually the same... usually.
     */
    const RENAME_STATEMENT = "ALTER TABLE {table} RENAME TO {name}";

    /**
     * Indication that table is exists and current schema is fetched from database.
     *
     * @var bool
     */
    protected $exists = false;

    /**
     * Table name including table prefix.
     *
     * @var string|AbstractColumn
     */
    protected $name = '';

    /**
     * Database specific tablePrefix. Required for table renames.
     *
     * @var string
     */
    protected $tablePrefix = '';

    /**
     * Primary key columns are stored separately from other indexes and can be modified only during
     * table creation.
     *
     * @var array
     */
    protected $primaryKeys = [];

    /**
     * Primary keys fetched from database.
     *
     * @invisible
     * @var array
     */
    protected $dbPrimaryKeys = [];

    /**
     * Column schemas fetched from db or created by user.
     *
     * @var AbstractColumn[]
     */
    protected $columns = [];

    /**
     * Column schemas fetched from db. Synced with $columns in table save() method.
     *
     * @invisible
     * @var AbstractColumn[]
     */
    protected $dbColumns = [];

    /**
     * Index schemas fetched from db or created by user.
     *
     * @var AbstractIndex[]
     */
    protected $indexes = [];

    /**
     * Index schemas fetched from db. Synced with $indexes in table save() method.
     *
     * @invisible
     * @var AbstractIndex[]
     */
    protected $dbIndexes = [];

    /**
     * Foreign key schemas fetched from db or created by user.
     *
     * @var AbstractReference[]
     */
    protected $references = [];

    /**
     * Foreign key schemas fetched from db. Synced with $references in table save() method.
     *
     * @invisible
     * @var AbstractReference[]
     */
    protected $dbReferences = [];

    /**
     * @invisible
     * @var Driver
     */
    protected $driver = null;

    /**
     * @param string $name        Table name, must include table prefix.
     * @param string $tablePrefix Database specific table prefix.
     * @param Driver $driver
     */
    public function __construct($name, $tablePrefix, Driver $driver)
    {
        $this->name = $name;
        $this->tablePrefix = $tablePrefix;
        $this->driver = $driver;

        if (!$this->driver->hasTable($this->name)) {
            return;
        }

        //Loading table information
        $this->loadColumns();
        $this->loadIndexes();
        $this->loadReferences();

        $this->exists = true;
    }

    /**
     * @return Driver
     */
    public function driver()
    {
        return $this->driver;
    }

    /**
     * {@inheritdoc}
     */
    public function exists()
    {
        return $this->exists;
    }

    /**
     * {@inheritdoc}
     *
     * @param bool $quoted Quote name.
     */
    public function getName($quoted = false)
    {
        return $quoted ? $this->driver->identifier($this->name) : $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function getPrimaryKeys()
    {
        return $this->primaryKeys;
    }

    /**
     * {@inheritdoc}
     */
    public function hasColumn($name)
    {
        return isset($this->columns[$name]);
    }

    /**
     * {@inheritdoc}
     *
     * @return AbstractColumn[]
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * {@inheritdoc}
     */
    public function hasIndex(array $columns = [])
    {
    }

    /**
     * {@inheritdoc}
     *
     * @return AbstractIndex[]
     */
    public function getIndexes()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function hasForeign($column)
    {
    }

    /**
     * {@inheritdoc}
     *
     * @return AbstractReference[]
     */
    public function getForeigns()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getDependencies()
    {
    }



    ///--------------------

    /**
     * Driver specific method to load table columns schemas.  Method will not be called if table not
     * exists. To create and register column schema use internal table method "registerColumn()".
     **/
    abstract protected function loadColumns();

    /**
     * Create and register ColumnSchema by provided column name and driver specific column information.
     * This method will automatically create ColumnSchema in both "columns" and "dbColumns" properties.
     *
     * @param string $name   Column name.
     * @param mixed  $schema Driver specific column information schema.
     * @return AbstractColumn
     */
    protected function registerColumn($name, $schema)
    {
        $column = $this->driver->columnSchema($this, $name, $schema);
        $this->dbColumns[$name] = clone $column;

        return $this->columns[$name] = $column;
    }

    /**
     * Driver specific method to load table indexes schema(s). Method will not be called if table
     * not exists. To create* and register index schema use internal table method "registerIndex()".
     */
    abstract protected function loadIndexes();

    /**
     * Create and register IndexSchema by provided index name and driver specific information. This
     * method will automatically create IndexSchema in both "indexes" and "dbIndexes" properties.
     *
     * @param string $name   Index name.
     * @param mixed  $schema Driver specific index information schema.
     * @return AbstractIndex
     */
    protected function registerIndex($name, $schema)
    {
        $index = $this->driver->indexSchema($this, $name, $schema);
        $this->dbIndexes[$name] = clone $index;

        return $this->indexes[$name] = $index;
    }

    /**
     * Driver specific method to load table foreign key schema(s). Method will not be called if table
     * not exists. To create and register reference (foreign key) schema use internal table method
     * "registerReference()".
     */
    abstract protected function loadReferences();

    /**
     * Create and register ReferenceSchema by provided foreign keyF name and driver specific information.
     * This method will automatically create ReferenceSchema in both "references" and "dbReferences"
     * properties.
     *
     * @param string $name   Foreign key name.
     * @param mixed  $schema Driver specific foreign key information schema.
     * @return AbstractReference
     */
    protected function registerReference($name, $schema)
    {
        $reference = $this->driver->referenceSchema($this, $name, $schema);
        $this->dbReferences[$name] = clone $reference;

        return $this->references[$name] = $reference;
    }

    ///--------------------

    /**
     * Find index using it's forming columns.
     *
     * @param array $columns
     * @return AbstractIndex|null
     */
    private function findIndex(array $columns)
    {
        foreach ($this->indexes as $index) {
            if ($index->getColumns() == $columns) {
                return $index;
            }
        }

        return null;
    }
}