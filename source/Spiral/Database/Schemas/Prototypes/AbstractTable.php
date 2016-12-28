<?php
/**
 * Spiral Framework, Core Components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Database\Schemas\Prototypes;

use Psr\Log\LoggerInterface;
use Spiral\Database\Entities\AbstractHandler as Behaviour;
use Spiral\Database\Entities\Driver;
use Spiral\Database\Exceptions\HandlerException;
use Spiral\Database\Exceptions\SchemaException;
use Spiral\Database\Schemas\StateComparator;
use Spiral\Database\Schemas\TableInterface;
use Spiral\Database\Schemas\TableState;

/**
 * AbstractTable class used to describe and manage state of specified table. It provides ability to
 * get table introspection, update table schema and automatically generate set of diff operations.
 *
 * Most of table operation like column, index or foreign key creation/altering will be applied when
 * save() method will be called.
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
 * @method AbstractColumn json($column)
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
abstract class AbstractTable implements TableInterface
{
    /**
     * Table states.
     */
    const STATUS_NEW     = 0;
    const STATUS_EXISTS  = 1;
    const STATUS_DROPPED = 2;

    /**
     * Indication that table is exists and current schema is fetched from database.
     *
     * @var int
     */
    private $status = self::STATUS_NEW;

    /**
     * Database specific tablePrefix. Required for table renames.
     *
     * @var string
     */
    private $prefix = '';

    /**
     * @invisible
     *
     * @var Driver
     */
    protected $driver = null;

    /**
     * Initial table state.
     *
     * @invisible
     * @var TableState
     */
    protected $initial = null;

    /**
     * Currently defined table state.
     *
     * @invisible
     * @var TableState
     */
    protected $current = null;

    /**
     * @param Driver $driver Parent driver.
     * @param string $name   Table name, must include table prefix.
     * @param string $prefix Database specific table prefix.
     */
    public function __construct(Driver $driver, string $name, string $prefix)
    {
        $this->driver = $driver;
        $this->prefix = $prefix;

        //Initializing states
        $this->initial = new TableState($this->prefix . $name);
        $this->current = new TableState($this->prefix . $name);

        if ($this->driver->hasTable($this->getName())) {
            $this->status = self::STATUS_EXISTS;
        }

        if ($this->exists()) {
            //Initiating table schema
            $this->initSchema($this->initial);
        }

        $this->setState($this->initial);
    }

    /**
     * Get instance of associated driver.
     *
     * @return Driver
     */
    public function getDriver(): Driver
    {
        return $this->driver;
    }

    /**
     * Return database specific table prefix.
     *
     * @return string
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * @return StateComparator
     */
    public function getComparator(): StateComparator
    {
        return new StateComparator($this->initial, $this->current);
    }

    /**
     * Check if table schema has been modified since synchronization.
     *
     * @return bool
     */
    protected function hasChanges(): bool
    {
        return $this->getComparator()->hasChanges();
    }

    /**
     * {@inheritdoc}
     */
    public function exists(): bool
    {
        return $this->status == self::STATUS_EXISTS || $this->status == self::STATUS_DROPPED;
    }

    /**
     * Table status (see codes above).
     *
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * Sets table name. Use this function in combination with save to rename table.
     *
     * @param string $name
     *
     * @return string Prefixed table name.
     */
    public function setName(string $name): string
    {
        $this->current->setName($this->prefix . $name);

        return $this->getName();
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->current->getName();
    }

    /**
     * Table name before rename.
     *
     * @return string
     */
    public function getInitialName(): string
    {
        return $this->initial->getName();
    }

    /**
     * Declare table as dropped, you have to sync table using "save" method in order to apply this
     * change.
     */
    public function declareDropped()
    {
        if ($this->status == self::STATUS_NEW) {
            throw new SchemaException("Unable to drop non existed table");
        }

        //Declaring as dropper
        $this->status = self::STATUS_DROPPED;
    }

    /**
     * Set table primary keys. Operation can only be applied for newly created tables. Now every
     * database might support compound indexes.
     *
     * @param array $columns
     *
     * @return self
     */
    public function setPrimaryKeys(array $columns): AbstractTable
    {
        //Originally i were forcing an exception when primary key were changed, now we should
        //force it when table will be synced

        //Updating primary keys in current state
        $this->current->setPrimaryKeys($columns);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getPrimaryKeys(): array
    {
        return $this->current->getPrimaryKeys();
    }

    /**
     * {@inheritdoc}
     */
    public function hasColumn(string $name): bool
    {
        return $this->current->hasColumn($name);
    }

    /**
     * {@inheritdoc}
     *
     * @return AbstractColumn[]
     */
    public function getColumns(): array
    {
        return $this->current->getColumns();
    }

    /**
     * {@inheritdoc}
     */
    public function hasIndex(array $columns = []): bool
    {
        return $this->current->hasIndex($columns);
    }

    /**
     * {@inheritdoc}
     *
     * @return AbstractIndex[]
     */
    public function getIndexes(): array
    {
        return $this->current->getIndexes();
    }

    /**
     * {@inheritdoc}
     */
    public function hasForeign(string $column): bool
    {
        return $this->current->hasForeign($column);
    }

    /**
     * {@inheritdoc}
     *
     * @return AbstractReference[]
     */
    public function getForeigns(): array
    {
        return $this->current->getForeigns();
    }

    /**
     * {@inheritdoc}
     */
    public function getDependencies(): array
    {
        $tables = [];
        foreach ($this->current->getForeigns() as $foreign) {
            $tables[] = $foreign->getForeignTable();
        }

        return $tables;
    }

    /**
     * Get/create instance of AbstractColumn associated with current table.
     *
     * Attention, renamed column will be available by it's old name until being synced!
     *
     * Examples:
     * $table->column('name')->string();
     *
     * @param string $name
     *
     * @return AbstractColumn
     */
    public function column(string $name): AbstractColumn
    {
        if ($this->current->hasColumn($name)) {
            //Column already exists
            return $this->current->findColumn($name);
        }

        $column = $this->createColumn($name);
        $this->current->registerColumn($column);

        return $column;
    }

    /**
     * Shortcut for column() method.
     *
     * @param string $column
     *
     * @return AbstractColumn
     */
    public function __get(string $column)
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
    public function __call(string $type, array $arguments)
    {
        return call_user_func_array(
            [$this->column($arguments[0]), $type],
            array_slice($arguments, 1)
        );
    }

    /**
     * Get/create instance of AbstractIndex associated with current table based on list of forming
     * column names.
     *
     * Example:
     * $table->index(['key']);
     * $table->index(['key', 'key2']);
     *
     * @param array $columns List of index columns.
     *
     * @return AbstractIndex
     *
     * @throws SchemaException
     */
    public function index(array $columns): AbstractIndex
    {
        foreach ($columns as $column) {
            if (!$this->hasColumn($column)) {
                throw new SchemaException("Undefined column '{$column}' in '{$this->getName()}'");
            }
        }

        if ($this->hasIndex($columns)) {
            return $this->current->findIndex($columns);
        }

        $index = $this->createIndex($this->createIdentifier('index', $columns));
        $index->columns($columns);
        $this->current->registerIndex($index);

        return $index;
    }

    /**
     * Get/create instance of AbstractReference associated with current table based on local column
     * name.
     *
     * @param string $column
     *
     * @return AbstractReference
     *
     * @throws SchemaException
     */
    public function foreign(string $column): AbstractReference
    {
        if (!$this->hasColumn($column)) {
            throw new SchemaException("Undefined column '{$column}' in '{$this->getName()}'");
        }

        if ($this->hasForeign($column)) {
            return $this->current->findForeign($column);
        }

        $foreign = $this->createForeign($this->createIdentifier('foreign', [$column]));
        $foreign->column($column);

        $this->current->registerReference($foreign);

        //Let's ensure index existence to performance and compatibility reasons
        $this->index([$column]);

        return $foreign;
    }

    /**
     * Rename column (only if column exists).
     *
     * @param string $column
     * @param string $name New column name.
     *
     * @return self
     *
     * @throws SchemaException
     */
    public function renameColumn(string $column, string $name): AbstractTable
    {
        if (!$this->hasColumn($column)) {
            throw new SchemaException("Undefined column '{$column}' in '{$this->getName()}'");
        }

        //Rename operation is simple about declaring new name
        $this->column($column)->setName($name);

        return $this;
    }

    /**
     * Rename index (only if index exists).
     *
     * @param array  $columns Index forming columns.
     * @param string $name    New index name.
     *
     * @return self
     *
     * @throws SchemaException
     */
    public function renameIndex(array $columns, string $name): AbstractTable
    {
        if ($this->hasIndex($columns)) {
            throw new SchemaException(
                "Undefined index ['" . join("', '", $columns) . "'] in '{$this->getName()}'"
            );
        }

        //Declaring new index name
        $this->index($columns)->setName($name);

        return $this;
    }

    /**
     * Drop column by it's name.
     *
     * @param string $column
     *
     * @return self
     *
     * @throws SchemaException
     */
    public function dropColumn(string $column): AbstractTable
    {
        if (empty($schema = $this->current->findColumn($column))) {
            throw new SchemaException("Undefined column '{$column}' in '{$this->getName()}'");
        }

        //Dropping column from current schema
        $this->current->forgetColumn($schema);

        return $this;
    }

    /**
     * Drop index by it's forming columns.
     *
     * @param array $columns
     *
     * @return self
     *
     * @throws SchemaException
     */
    public function dropIndex(array $columns): AbstractTable
    {
        if (empty($schema = $this->current->findIndex($columns))) {
            throw new SchemaException(
                "Undefined index ['" . join("', '", $columns) . "'] in '{$this->getName()}'"
            );
        }

        //Dropping index from current schema
        $this->current->forgetIndex($schema);

        return $this;
    }

    /**
     * Drop foreign key by it's name.
     *
     * @param string $column
     *
     * @return self
     *
     * @throws SchemaException
     */
    public function dropForeign($column): AbstractTable
    {
        if (empty($schema = $this->current->findForeign($column))) {
            throw new SchemaException(
                "Undefined FK on '{$column}' in '{$this->getName()}'"
            );
        }

        //Dropping foreign from current schema
        $this->current->forgetForeign($schema);

        return $this;
    }

    /**
     * Get current table state (detached).
     *
     * @return TableState
     */
    public function getState(): TableState
    {
        $state = clone $this->current;
        $state->remountElements();

        return $state;
    }

    /**
     * Reset table state to new form.
     *
     * @param TableState $state Use null to flush table schema.
     *
     * @return self|$this
     */
    public function setState(TableState $state = null): AbstractTable
    {
        $this->current = new TableState($this->initial->getName());

        if (!empty($state)) {
            $this->current->setName($state->getName());
            $this->current->syncState($state);
        }

        return $this;
    }

    /**
     * Reset table state to it initial form.
     *
     * @return self|$this
     */
    public function resetState(): AbstractTable
    {
        $this->setState($this->initial);

        return $this;
    }

    /**
     * Save table schema including every column, index, foreign key creation/altering. If table
     * does not exist it must be created. If table declared as dropped it will be removed from
     * the database.
     *
     * @param int             $behaviour Operation to be performed while table being saved. In some
     *                                   cases (when multiple tables are being updated) it is
     *                                   reasonable to drop foreing keys and indexes prior to
     *                                   dropping related columns. See sync bus class to get more
     *                                   details.
     * @param LoggerInterface $logger    Optional, aggregates messages for data syncing.
     * @param bool            $reset     When true schema will be marked as synced.
     *
     * @throws HandlerException
     *
     * @throws SchemaException
     */
    public function save(
        int $behaviour = Behaviour::DO_ALL,
        LoggerInterface $logger = null,
        bool $reset = true
    ) {
        //We need an instance of Handler of dbal operations
        $handler = $this->driver->getHandler($logger);

        if ($this->status == self::STATUS_DROPPED) {
            //We don't need syncer for this operation
            $handler->dropTable($this);

            //Flushing status
            $this->status = self::STATUS_NEW;

            return;
        }

        //Ensure that columns references to valid indexes and et
        $prepared = $this->prepareSchema();

        if ($this->status == self::STATUS_NEW) {
            //Executing table creation
            $handler->createTable($prepared);
            $this->status = self::STATUS_EXISTS;
        } else {
            //Executing table syncing
            if ($this->hasChanges()) {
                $handler->syncTable($prepared, $behaviour);
            }

            $prepared->status = self::STATUS_EXISTS;
        }

        //Syncing our schemas
        if ($reset) {
            $this->initial->syncState($prepared->current);
        }
    }

    /**
     * Ensure that no wrong indexes left in table.
     *
     * @return AbstractTable
     */
    protected function prepareSchema()
    {
        //To make sure that no pre-sync modifications will be reflected on current table
        $target = clone $this;

        /*
         * In cases where columns are removed we have to automatically remove related indexes and
         * foreign keys.
         */
        foreach ($this->getComparator()->droppedColumns() as $column) {
            foreach ($target->getIndexes() as $index) {
                if (in_array($column->getName(), $index->getColumns())) {
                    $target->current->forgetIndex($index);
                }
            }

            foreach ($target->getForeigns() as $foreign) {
                if ($column->getName() == $foreign->getColumn()) {
                    $target->current->forgetForeign($foreign);
                }
            }
        }

        //We also have to adjusts indexes and foreign keys
        foreach ($this->getComparator()->alteredColumns() as $pair) {
            /**
             * @var AbstractColumn $initial
             * @var AbstractColumn $name
             */
            list($name, $initial) = $pair;

            foreach ($target->getIndexes() as $index) {
                if (in_array($initial->getName(), $index->getColumns())) {
                    $columns = $index->getColumns();

                    //Replacing column name
                    foreach ($columns as &$column) {
                        if ($column == $initial->getName()) {
                            $column = $name->getName();
                        }

                        unset($column);
                    }

                    $index->columns($columns);
                }
            }

            foreach ($target->getForeigns() as $foreign) {
                if ($initial->getName() == $foreign->getColumn()) {
                    $foreign->column($name->getName());
                }
            }
        }

        return $target;
    }

    /**
     * @return AbstractColumn|string
     */
    public function __toString(): string
    {
        return $this->getName();
    }

    /**
     * Cloning schemas as well.
     */
    public function __clone()
    {
        $this->initial = clone $this->initial;
        $this->current = clone $this->current;
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
     * Populate table schema with values from database.
     *
     * @param TableState $state
     */
    protected function initSchema(TableState $state)
    {
        foreach ($this->fetchColumns() as $column) {
            $state->registerColumn($column);
        }

        foreach ($this->fetchIndexes() as $index) {
            $state->registerIndex($index);
        }

        foreach ($this->fetchReferences() as $foreign) {
            $state->registerReference($foreign);
        }

        $state->setPrimaryKeys($this->fetchPrimaryKeys());

        //DBMS specific initialization can be placed here
    }

    /**
     * Fetch index declarations from database.
     *
     * @return AbstractColumn[]
     */
    abstract protected function fetchColumns(): array;

    /**
     * Fetch index declarations from database.
     *
     * @return AbstractIndex[]
     */
    abstract protected function fetchIndexes(): array;

    /**
     * Fetch references declaration from database.
     *
     * @return AbstractReference[]
     */
    abstract protected function fetchReferences(): array;

    /**
     * Fetch names of primary keys from table.
     *
     * @return array
     */
    abstract protected function fetchPrimaryKeys(): array;

    /**
     * Create column with a given name.
     *
     * @param string $name
     *
     * @return AbstractColumn
     */
    abstract protected function createColumn(string $name): AbstractColumn;

    /**
     * Create index for a given set of columns.
     *
     * @param string $name
     *
     * @return AbstractIndex
     */
    abstract protected function createIndex(string $name): AbstractIndex;

    /**
     * Create reference on a given column set.
     *
     * @param string $name
     *
     * @return AbstractReference
     */
    abstract protected function createForeign(string $name): AbstractReference;

    /**
     * Generate unique name for indexes and foreign keys.
     *
     * @param string $type
     * @param array  $columns
     *
     * @return string
     */
    protected function createIdentifier(string $type, array $columns): string
    {
        $name = $this->getName() . '_' . $type . '_' . join('_', $columns) . '_' . uniqid();

        if (strlen($name) > 64) {
            //Many DBMS has limitations on identifier length
            $name = md5($name);
        }

        return $name;
    }
}