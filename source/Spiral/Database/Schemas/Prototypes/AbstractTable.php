<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Database\Schemas\Prototypes;

use Interop\Container\ContainerInterface;
use Psr\Log\LoggerAwareInterface;
use Spiral\Core\Component;
use Spiral\Database\Entities\Driver;
use Spiral\Database\Exceptions\SchemaException;
use Spiral\Database\Schemas\ColumnInterface;
use Spiral\Database\Schemas\IndexInterface;
use Spiral\Database\Schemas\ReferenceInterface;
use Spiral\Database\Schemas\StateComparator;
use Spiral\Database\Schemas\TableInterface;
use Spiral\Database\Schemas\TableState;
use Spiral\Debug\Traits\LoggerTrait;

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
abstract class AbstractTable extends Component implements TableInterface, LoggerAwareInterface
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
    protected $initialState = null;

    /**
     * Currently defined table state.
     *
     * @invisible
     * @var TableState
     */
    protected $currentState = null;

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
        $this->initialState = new TableState($this->prefix . $name);
        $this->currentState = new TableState($this->prefix . $name);

        $this->exists = $this->driver->hasTable($this->getName());

        if ($this->exists) {
            //Initiating table schema
            $this->initSchema($this->initialState);
        }

        $this->setState($this->initialState);
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
     * @return StateComparator
     */
    public function getComparator(): StateComparator
    {
        return new StateComparator($this->initialState, $this->currentState);
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
        return $this->exists;
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
     * Sets table name. Use this function in combination with save to rename table.
     *
     * @param string $name
     *
     * @return string Prefixed table name.
     */
    public function setName(string $name): string
    {
        $this->currentState->setName($this->prefix . $name);

        return $this->getName();
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->currentState->getName();
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
        //Originally we were forcing an exception when primary key were changed, now we should
        //force it when table will be synced

        //Updating primary keys in current state
        $this->currentState->setPrimaryKeys($columns);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getPrimaryKeys(): array
    {
        return $this->currentState->getPrimaryKeys();
    }

    /**
     * {@inheritdoc}
     */
    public function hasColumn(string $name): bool
    {
        return $this->currentState->hasColumn($name);
    }

    /**
     * {@inheritdoc}
     *
     * @return ColumnInterface[]|AbstractColumn[]
     */
    public function getColumns(): array
    {
        return $this->currentState->getColumns();
    }

    /**
     * {@inheritdoc}
     */
    public function hasIndex(array $columns = []): bool
    {
        return $this->currentState->hasIndex($columns);
    }

    /**
     * {@inheritdoc}
     *
     * @return IndexInterface[]|AbstractIndex[]
     */
    public function getIndexes(): array
    {
        return $this->currentState->getIndexes();
    }

    /**
     * {@inheritdoc}
     */
    public function hasForeign(string $column): bool
    {
        return $this->currentState->hasForeign($column);
    }

    /**
     * {@inheritdoc}
     *
     * @return ReferenceInterface[]|AbstractReference[]
     */
    public function getForeigns(): array
    {
        return $this->currentState->getForeigns();
    }

    /**
     * {@inheritdoc}
     */
    public function getDependencies(): array
    {
        $tables = [];
        foreach ($this->currentState->getForeigns() as $foreign) {
            $tables[] = $foreign->getForeignTable();
        }

        return $tables;
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
    public function column(string $name): AbstractColumn
    {
        if ($this->currentState->hasColumn($name)) {
            //Column already exists
            return $this->currentState->findColumn($name);
        }

        $column = $this->createColumn($name);
        $this->currentState->registerColumn($column);

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
     * $table->index('key');
     * $table->index('key', 'key2');
     * $table->index(['key', 'key2']);
     *
     * @param mixed $columns Column name, or array of columns.
     *
     * @return AbstractIndex
     *
     * @throws SchemaException
     */
    public function index($columns): AbstractIndex
    {
        $columns = is_array($columns) ? $columns : func_get_args();

        foreach ($columns as $column) {
            if (!$this->currentState->hasColumn($column)) {
                throw new SchemaException("Undefined column '{$column}' of '{$this->getName()}'");
            }
        }

        if ($this->currentState->hasIndex($columns)) {
            return $this->currentState->findIndex($columns);
        }

        $index = $this->createIndex($this->createIdentifier('index', $columns));
        $index->columns($columns);
        $this->currentState->registerIndex($index);

        return $index;
    }

    /**
     * Get/create instance of AbstractReference associated with current table based on local column
     * name.
     *
     * @param string $column
     *
     * @return AbstractReference
     */
    public function foreign(string $column): AbstractReference
    {
        if (!$this->currentState->hasColumn($column)) {
            throw new SchemaException("Undefined column '{$column}' of '{$this->getName()}'");
        }

        if ($this->currentState->hasForeign($column)) {
            return $this->currentState->findForeign($column);
        }

        $foreign = $this->createReference($this->createIdentifier('foreign', [$column]));
        $foreign->column($column);
        $this->currentState->registerReference($foreign);

        //Let's ensure index existence
        $this->index($column);

        return $foreign;
    }

    //------
    //Altering operations
    //------

    /**
     * Reset table state to new form.
     *
     * @param TableState $state Use null to flush table schema.
     *
     * @return self|$this
     */
    public function setState(TableState $state = null): AbstractTable
    {
        $this->currentState = new TableState($this->initialState->getName());

        if (!empty($state)) {
            $this->currentState->setName($state->getName());
            $this->currentState->syncState($state);
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
        $this->setState($this->initialState);

        return $this;
    }

    /**
     * @return AbstractColumn|string
     */
    public function __toString(): string
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
     * @return ColumnInterface[]
     */
    abstract protected function fetchColumns(): array;

    /**
     * Fetch index declarations from database.
     *
     * @return IndexInterface[]
     */
    abstract protected function fetchIndexes(): array;

    /**
     * Fetch references declaration from database.
     *
     * @return ReferenceInterface[]
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
    abstract protected function createReference(string $name): AbstractReference;

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

    /**
     * @return ContainerInterface
     */
    protected function iocContainer()
    {
        //Falling back to driver specific container
        return $this->driver->iocContainer();
    }
}