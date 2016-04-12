<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Entities\Schemas;

use Psr\Log\LoggerAwareInterface;
use Spiral\Database\Entities\Driver;
use Spiral\Database\Schemas\ColumnInterface;
use Spiral\Database\Schemas\TableInterface;
use Spiral\Debug\Traits\LoggerTrait;
use Spiral\ODM\Exceptions\SchemaException;

/**
 * AbstractTable class used to describe and manage state of specified table. It provides ability to
 * get table introspection, update table schema and automatically generate set of diff operations.
 *
 * Most of table operation like column, index or foreign key creation/altering will be applied when
 * save() method will be called.
 *
 * @todo Split operations and state representation.
 *
 * Column configuration shortcuts:
 *
 * @method AbstractColumn primary($column)
 * @method AbstractColumn bigPrimary($column)
 * @method AbstractColumn enum($column, array $values)
 * @method AbstractColumn string($column, $length = 255)
 * @method AbstractColumn decimal($column, $precision, $scale)
 * @method AbstractColumn boolean($column)
 * @method AbstractColumn integer($column)
 * @method AbstractColumn tinyInteger($column)
 * @method AbstractColumn bigInteger($column)
 * @method AbstractColumn text($column)
 * @method AbstractColumn tinyText($column)
 * @method AbstractColumn longText($column)
 * @method AbstractColumn double($column)
 * @method AbstractColumn float($column)
 * @method AbstractColumn datetime($column)
 * @method AbstractColumn date($column)
 * @method AbstractColumn time($column)
 * @method AbstractColumn timestamp($column)
 * @method AbstractColumn binary($column)
 * @method AbstractColumn tinyBinary($column)
 * @method AbstractColumn longBinary($column)
 */
abstract class AbstractTable extends TableState implements TableInterface, LoggerAwareInterface
{
    use LoggerTrait;

    /**
     * Indication that table is exists and current schema is fetched from database.
     *
     * @var bool
     */
    private $exists = false;

    /**
     * Database specific tablePrefix. Required for table renames.
     *
     * @var string
     */
    private $prefix = '';

    /**
     * We have to remember original schema state to create set of diff based commands.
     *
     * @invisible
     *
     * @var TableState
     */
    protected $initial = null;

    /**
     * Compares current and original states.
     *
     * @invisible
     *
     * @var Comparator
     */
    protected $comparator = null;

    /**
     * @invisible
     *
     * @var Driver
     */
    protected $driver = null;

    /**
     * Executes table operations.
     *
     * @var AbstractCommander
     */
    protected $commander = null;

    /**
     * @param Driver            $driver Parent driver.
     * @param AbstractCommander $commander
     * @param string            $name   Table name, must include table prefix.
     * @param string            $prefix Database specific table prefix.
     */
    public function __construct(Driver $driver, AbstractCommander $commander, $name, $prefix)
    {
        parent::__construct($name);

        $this->driver = $driver;
        $this->commander = $commander;

        $this->prefix = $prefix;

        //Locking down initial table state
        $this->initial = new TableState($name);

        //Needed to compare schemas
        $this->comparator = new Comparator($this->initial, $this);

        if (!$this->driver->hasTable($this->getName())) {
            //There is no need to load table schema when table does not exist
            return;
        }

        //Loading table information
        $this->loadColumns()->loadIndexes()->loadReferences();

        //Syncing schemas
        $this->initial->syncSchema($this);

        $this->exists = true;
    }

    /**
     * Get associated table driver.
     *
     * @return Driver
     */
    public function getDriver()
    {
        return $this->driver;
    }

    /**
     * Get associated table driver.
     *
     * @deprecated see getDriver()
     * @return Driver
     */
    public function driver()
    {
        return $this->driver;
    }

    /**
     * Get table comparator.
     *
     * @return Comparator
     */
    public function getComparator()
    {
        return $this->comparator;
    }

    /**
     * Get table comparator.
     *
     * @deprecated See getComparator()
     * @return Comparator
     */
    public function comparator()
    {
        return $this->comparator;
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
     * Automatically forces prefix value.
     */
    public function setName($name)
    {
        parent::setName($this->prefix . $name);
    }

    /**
     * {@inheritdoc}
     *
     * @param bool $quoted Quote name.
     */
    public function getName($quoted = false)
    {
        if (!$quoted) {
            return parent::getName();
        }

        return $this->driver->identifier(parent::getName());
    }

    /**
     * Return database specific table prefix.
     *
     * @return string
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * {@inheritdoc}
     */
    public function getDependencies()
    {
        $tables = [];
        foreach ($this->getForeigns() as $foreign) {
            $tables[] = $foreign->getForeignTable();
        }

        return $tables;
    }

    /**
     * Set table primary keys. Operation can only be applied for newly created tables. Now every
     * database might support compound indexes.
     *
     * @param array $columns
     *
     * @return $this
     *
     * @throws SchemaException
     */
    public function setPrimaryKeys(array $columns)
    {
        if ($this->exists() && $this->getPrimaryKeys() != $columns) {
            throw new SchemaException('Unable to change primary keys for already exists table');
        }

        parent::setPrimaryKeys($columns);

        return $this;
    }

    /**
     * Get/create instance of AbstractColumn associated with current table.
     *
     * Examples:
     * $table->column('name')->string();
     *
     * @param string $name
     *
     * @return AbstractColumn
     */
    public function column($name)
    {
        if (!empty($column = $this->findColumn($name))) {
            return $column->declared(true);
        }

        $column = $this->columnSchema($name)->declared(true);

        //Registering (without adding to initial schema)
        return $this->registerColumn($column);
    }

    /**
     * Get/create instance of AbstractIndex associated with current table based on list of forming
     * column names.
     *
     * Example:
     * $table->index('key');
     * $table->index('key', 'key2');
     * $table->index(['key', 'key2']);
     *
     * @param mixed $columns Column name, or array of columns.
     *
     * @return AbstractIndex
     */
    public function index($columns)
    {
        $columns = is_array($columns) ? $columns : func_get_args();
        if (!empty($index = $this->findIndex($columns))) {
            return $index->declared(true);
        }

        $index = $this->indexSchema(null)->declared(true);
        $index->columns($columns)->unique(false);

        return $this->registerIndex($index);
    }

    /**
     * Get/create instance of AbstractIndex associated with current table based on list of forming
     * column names. Index type must be forced as UNIQUE.
     *
     * Example:
     * $table->unique('key');
     * $table->unique('key', 'key2');
     * $table->unique(['key', 'key2']);
     *
     * @param mixed $columns Column name, or array of columns.
     *
     * @return AbstractColumn|null
     */
    public function unique($columns)
    {
        $columns = is_array($columns) ? $columns : func_get_args();

        return $this->index($columns)->unique(true);
    }

    /**
     * Get/create instance of AbstractReference associated with current table based on local column
     * name.
     *
     * @param string $column Column name.
     *
     * @return AbstractReference|null
     */
    public function foreign($column)
    {
        if (!empty($foreign = $this->findForeign($column))) {
            return $foreign->declared(true);
        }

        $foreign = $this->referenceSchema(null)->declared(true);
        $foreign->column($column);

        return $this->registerReference($foreign);
    }

    /**
     * Rename column (only if column exists).
     *
     * @param string $column
     * @param string $name New column name.
     *
     * @return $this
     */
    public function renameColumn($column, $name)
    {
        if (empty($column = $this->findColumn($column))) {
            return $this;
        }

        //Renaming automatically declares column
        $column->declared(true)->setName($name);

        return $this;
    }

    /**
     * Rename index (only if index exists).
     *
     * @param array  $columns Index forming columns.
     * @param string $name    New index name.
     *
     * @return $this
     */
    public function renameIndex(array $columns, $name)
    {
        if (empty($index = $this->findIndex($columns))) {
            return $this;
        }

        //Renaming automatically declares index
        $index->declared(true)->setName($name);

        return $this;
    }

    /**
     * Drop column by it's name.
     *
     * @param string $column
     *
     * @return $this
     */
    public function dropColumn($column)
    {
        if (!empty($column = $this->findColumn($column))) {
            $this->forgetColumn($column);
            $this->removeDependent($column);
        }

        return $this;
    }

    /**
     * Drop index by it's forming columns.
     *
     * @param array $columns
     *
     * @return $this
     */
    public function dropIndex(array $columns)
    {
        if (!empty($index = $this->findIndex($columns))) {
            $this->forgetIndex($index);
        }

        return $this;
    }

    /**
     * Drop foreign key by it's name.
     *
     * @param string $column
     *
     * @return $this
     */
    public function dropForeign($column)
    {
        if (!empty($foreign = $this->findForeign($column))) {
            $this->forgetForeign($foreign);
        }

        return $this;
    }

    /**
     * Shortcut for column() method.
     *
     * @param string $column
     *
     * @return AbstractColumn
     */
    public function __get($column)
    {
        return $this->column($column);
    }

    /**
     * Column creation/altering shortcut, call chain is identical to:
     * AbstractTable->column($name)->$type($arguments).
     *
     * Example:
     * $table->string("name");
     * $table->text("some_column");
     *
     * @param string $type
     * @param array  $arguments Type specific parameters.
     *
     * @return AbstractColumn
     */
    public function __call($type, array $arguments)
    {
        return call_user_func_array(
            [$this->column($arguments[0]), $type],
            array_slice($arguments, 1)
        );
    }

    /**
     * Declare every existed element. Method has to be called if table modification applied to
     * existed table to prevent dropping of existed elements.
     *
     * @return $this
     */
    public function declareExisted()
    {
        foreach ($this->getColumns() as $column) {
            $column->declared(true);
        }

        foreach ($this->getIndexes() as $index) {
            $index->declared(true);
        }

        foreach ($this->getForeigns() as $foreign) {
            $foreign->declared(true);
        }

        return $this;
    }

    /**
     * Calculate difference (removed columns, indexes and foreign keys).
     *
     * @param bool $forgetColumns
     * @param bool $forgetIndexes
     * @param bool $forgetForeigns
     */
    public function forgetUndeclared($forgetColumns, $forgetIndexes, $forgetForeigns)
    {
        //We don't need to worry about changed or created columns, indexes and foreign keys here
        //as it already handled, we only have to drop columns which were not listed in schema

        foreach ($this->getColumns() as $column) {
            if ($forgetColumns && !$column->isDeclared()) {
                $this->forgetColumn($column);
                $this->removeDependent($column);
            }
        }

        foreach ($this->getIndexes() as $index) {
            if ($forgetIndexes && !$index->isDeclared()) {
                $this->forgetIndex($index);
            }
        }

        foreach ($this->getForeigns() as $foreign) {
            if ($forgetForeigns && !$foreign->isDeclared()) {
                $this->forgetForeign($foreign);
            }
        }
    }

    /**
     * Save table schema including every column, index, foreign key creation/altering. If table does
     * not exist it must be created.
     *
     * @param bool $forgetColumns  Drop all non declared columns.
     * @param bool $forgetIndexes  Drop all non declared indexes.
     * @param bool $forgetForeigns Drop all non declared foreign keys.
     */
    public function save($forgetColumns = true, $forgetIndexes = true, $forgetForeigns = true)
    {
        if (!$this->exists()) {
            $this->createSchema();
        } else {
            //Let's remove from schema elements which wasn't declared
            $this->forgetUndeclared($forgetColumns, $forgetIndexes, $forgetForeigns);

            if ($this->hasChanges()) {
                $this->synchroniseSchema();
            }
        }

        //Syncing internal states
        $this->initial->syncSchema($this);
        $this->exists = true;
    }

    /**
     * Drop table schema in database. This operation must be applied immediately.
     */
    public function drop()
    {
        $this->forgetElements();

        //Re-syncing initial state
        $this->initial->syncSchema($this->forgetElements());

        if ($this->exists()) {
            $this->commander->dropTable($this->getName());
        }

        $this->exists = false;
    }

    /**
     * @return AbstractColumn|string
     */
    public function __toString()
    {
        return $this->getName();
    }

    /**
     * @return array
     */
    public function __debugInfo()
    {
        return [
            'name'        => $this->getName(),
            'primaryKeys' => $this->getPrimaryKeys(),
            'columns'     => array_values($this->getColumns()),
            'indexes'     => array_values($this->getIndexes()),
            'references'  => array_values($this->getForeigns()),
        ];
    }

    /**
     * Create table.
     */
    protected function createSchema()
    {
        $this->logger()->debug('Creating new table {table}.', ['table' => $this->getName(true)]);

        $this->commander->createTable($this);
    }

    /**
     * Execute schema update.
     */
    protected function synchroniseSchema()
    {
        if ($this->getName() != $this->initial->getName()) {
            //Executing renaming
            $this->commander->renameTable($this->initial->getName(), $this->getName());
        }

        //Some data has to be dropped before column updates
        $this->dropForeigns()->dropIndexes();

        //Generate update flow
        $this->synchroniseColumns()->synchroniseIndexes()->synchroniseForeigns();
    }

    /**
     * Synchronise columns.
     *
     * @todo Split or isolate.
     * @return $this
     */
    protected function synchroniseColumns()
    {
        foreach ($this->comparator->droppedColumns() as $column) {
            $this->logger()->debug('Dropping column [{statement}] from table {table}.', [
                'statement' => $column->sqlStatement(),
                'table'     => $this->getName(true),
            ]);

            $this->commander->dropColumn($this, $column);
        }

        foreach ($this->comparator->addedColumns() as $column) {
            $this->logger()->debug('Adding column [{statement}] into table {table}.', [
                'statement' => $column->sqlStatement(),
                'table'     => $this->getName(true),
            ]);

            $this->commander->addColumn($this, $column);
        }

        foreach ($this->comparator->alteredColumns() as $pair) {
            /**
             * @var AbstractColumn $initial
             * @var AbstractColumn $current
             */
            list($current, $initial) = $pair;

            $this->logger()->debug('Altering column [{statement}] to [{new}] in table {table}.', [
                'statement' => $initial->sqlStatement(),
                'new'       => $current->sqlStatement(),
                'table'     => $this->getName(true),
            ]);

            $this->commander->alterColumn($this, $initial, $current);
        }

        return $this;
    }

    /**
     * Drop needed indexes.
     *
     * @return $this
     */
    protected function dropIndexes()
    {
        foreach ($this->comparator->droppedIndexes() as $index) {
            $this->logger()->debug('Dropping index [{statement}] from table {table}.', [
                'statement' => $index->sqlStatement(),
                'table'     => $this->getName(true),
            ]);

            $this->commander->dropIndex($this, $index);
        }

        return $this;
    }

    /**
     * Synchronise indexes.
     *
     * @return $this
     */
    protected function synchroniseIndexes()
    {
        foreach ($this->comparator->addedIndexes() as $index) {
            $this->logger()->debug('Adding index [{statement}] into table {table}.', [
                'statement' => $index->sqlStatement(),
                'table'     => $this->getName(true),
            ]);

            $this->commander->addIndex($this, $index);
        }

        foreach ($this->comparator->alteredIndexes() as $pair) {
            /**
             * @var AbstractIndex $initial
             * @var AbstractIndex $current
             */
            list($current, $initial) = $pair;

            $this->logger()->debug('Altering index [{statement}] to [{new}] in table {table}.', [
                'statement' => $initial->sqlStatement(),
                'new'       => $current->sqlStatement(),
                'table'     => $this->getName(true),
            ]);

            $this->commander->alterIndex($this, $initial, $current);
        }

        return $this;
    }

    /**
     * Drop needed foreign keys.
     *
     * @return $this
     */
    protected function dropForeigns()
    {
        foreach ($this->comparator->droppedForeigns() as $foreign) {
            $this->logger()->debug('Dropping foreign key [{statement}] from table {table}.', [
                'statement' => $foreign->sqlStatement(),
                'table'     => $this->getName(true),
            ]);

            $this->commander->dropForeign($this, $foreign);
        }

        return $this;
    }

    /**
     * Synchronise foreign keys.
     *
     * @return $this
     */
    protected function synchroniseForeigns()
    {
        foreach ($this->comparator->addedForeigns() as $foreign) {
            $this->logger()->debug('Adding foreign key [{statement}] into table {table}.', [
                'statement' => $foreign->sqlStatement(),
                'table'     => $this->getName(true),
            ]);

            $this->commander->addForeign($this, $foreign);
        }

        foreach ($this->comparator->alteredForeigns() as $pair) {
            /**
             * @var AbstractReference $initial
             * @var AbstractReference $current
             */
            list($current, $initial) = $pair;

            $this->logger()->debug('Altering foreign key [{statement}] to [{new}] in {table}.', [
                'statement' => $initial->sqlStatement(),
                'table'     => $this->getName(true),
            ]);

            $this->commander->alterForeign($this, $initial, $current);
        }

        return $this;
    }

    /**
     * Driver specific column schema.
     *
     * @param string $name
     * @param mixed  $schema
     *
     * @return AbstractColumn
     */
    abstract protected function columnSchema($name, $schema = null);

    /**
     * Driver specific index schema.
     *
     * @param string $name
     * @param mixed  $schema
     *
     * @return AbstractIndex
     */
    abstract protected function indexSchema($name, $schema = null);

    /**
     * Driver specific reference schema.
     *
     * @param string $name
     * @param mixed  $schema
     *
     * @return AbstractReference
     */
    abstract protected function referenceSchema($name, $schema = null);

    /**
     * Must load table columns.
     *
     * @see registerColumn()
     *
     * @return self
     */
    abstract protected function loadColumns();

    /**
     * Must load table indexes.
     *
     * @see registerIndex()
     *
     * @return self
     */
    abstract protected function loadIndexes();

    /**
     * Must load table references.
     *
     * @see registerReference()
     *
     * @return self
     */
    abstract protected function loadReferences();

    /**
     * Check if table schema has been modified. Attention, you have to execute dropUndeclared first
     * to get valid results.
     *
     * @return bool
     */
    protected function hasChanges()
    {
        return $this->comparator->hasChanges();
    }

    /**
     * Remove dependent indexes and foreign keys.
     *
     * @param ColumnInterface $column
     */
    private function removeDependent(ColumnInterface $column)
    {
        if ($this->hasForeign($column->getName())) {
            $this->forgetForeign($this->foreign($column->getName()));
        }

        foreach ($this->getIndexes() as $index) {
            if (in_array($column->getName(), $index->getColumns())) {
                //Dropping related indexes
                $this->forgetIndex($index);
            }
        }
    }

    /**
     * Forget all elements.
     *
     * @return $this
     */
    private function forgetElements()
    {
        foreach ($this->getColumns() as $column) {
            $this->forgetColumn($column);
        }

        foreach ($this->getIndexes() as $index) {
            $this->forgetIndex($index);
        }

        foreach ($this->getForeigns() as $foreign) {
            $this->forgetForeign($foreign);
        }

        return $this;
    }
}
