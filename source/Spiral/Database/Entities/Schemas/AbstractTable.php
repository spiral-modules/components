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
     * Fully clarified table name (prefix should be included).
     *
     * Attention! BaseColumnSchema type added to make IDE work properly as "name" is really common
     * column name. However you better use longer syntax $table->column('name');
     *
     * @var string|AbstractColumn
     */
    protected $name = '';

    /**
     * Table prefix is not required, but if provided all foreign keys will be created using it.
     *
     * @var string
     */
    protected $tablePrefix = '';


    /**
     * Driver instance table schema associated with, all commands will be performed using it.
     *
     * @var Driver
     */
    protected $driver = null;

    /**
     * Primary key columns are stored separately from other indexes and can be modified only during
     * table creation. Column types "primary" and "bigPrimary" will automatically ensure it's column
     * name in that index, however for most database drivers that types will additionally declare
     * auto-incrementing which can be applied to one column only. To create compound primary index
     * call primaryKeys() method on table level. Attention, Spiral ORM and ActiveRecord models can
     * not support compound primary keys.
     *
     * @var array
     */
    protected $primaryKeys = [];

    /**
     * Column names fetched from database table and used to build primary index. Primary index can
     * not be modified for already exists tables.
     *
     * @invisible
     * @var array
     */
    protected $dbPrimaryKeys = [];

    /**
     * ColumnSchema(s) describing table columns, represents desired table structure to be applied
     * on save() method.
     *
     * @var AbstractColumn[]
     */
    protected $columns = [];

    /**
     * ColumnSchema(s) fetched from database (if table exists), this schemas used as column references
     * to build table diff.
     *
     * @invisible
     * @var AbstractColumn[]
     */
    protected $dbColumns = [];

    /**
     * IndexSchema(s) used to described desired table indexes, this schemas will be synced with
     * database on save() method call. IndexSchemas should not include primary keys.
     *
     * @var AbstractIndex[]
     */
    protected $indexes = [];

    /**
     * IndexSchema(s) fetched from database, this indexes used as references to build table diff.
     *
     * @invisible
     * @var AbstractIndex[]
     */
    protected $dbIndexes = [];

    /**
     * ReferenceSchema(s) used to define table foreign key references, this schemas will be applied
     * to database on save() method call. ReferenceSchemas table name depends on tablePrefix, make
     * sure correct value were specified.
     *
     * @var AbstractReference[]
     */
    protected $references = [];

    /**
     * ReferenceSchema(s) fetched from database and used to build table diff.
     *
     * @invisible
     * @var AbstractReference[]
     */
    protected $dbReferences = [];

    /**
     * @param string $name        Fully clarified table name (prefix should be included).
     * @param string $tablePrefix Table prefix is not required, but if provided all foreign keys
     *                            will be created using it.
     * @param Driver $driver      Driver instance table schema associated with, all commands will
     *                            be performed using it).
     */
    public function __construct($name, $tablePrefix, Driver $driver)
    {
        $this->name = $name;
        $this->tablePrefix = $tablePrefix;
        $this->driver = $driver;

        //Loading table information
        if ($this->driver->hasTable($this->name)) {
            $this->loadColumns();
            $this->loadIndexes();
            $this->loadReferences();

            $this->exists = true;
        }
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
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function getPrimaryKeys()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function hasColumn($name)
    {
    }

    /**
     * {@inheritdoc}
     *
     * @return AbstractColumn[]
     */
    public function getColumns()
    {
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


}