<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\Database\Entities;

use Psr\Log\LoggerInterface;
use Spiral\Core\Exceptions\InvalidArgumentException;
use Spiral\Database\Exceptions\DBALException;
use Spiral\Database\Exceptions\DriverException;
use Spiral\Database\Exceptions\QueryException;
use Spiral\Database\Exceptions\SchemaHandlerException;
use Spiral\Database\Schemas\Prototypes\AbstractColumn;
use Spiral\Database\Schemas\Prototypes\AbstractElement;
use Spiral\Database\Schemas\Prototypes\AbstractIndex;
use Spiral\Database\Schemas\Prototypes\AbstractReference;
use Spiral\Database\Schemas\Prototypes\AbstractTable;
use Spiral\Database\Schemas\StateComparator;

/**
 * Handler class implements set of DBMS specific operations for schema manipulations. Can be used
 * on separate basis (for example in migrations).
 */
abstract class AbstractHandler
{
    //Foreign key modification behaviours
    const DROP_FOREIGNS   = 0b000000001;
    const CREATE_FOREIGNS = 0b000000010;
    const ALTER_FOREIGNS  = 0b000000100;

    //All foreign keys related operations
    const DO_FOREIGNS = self::DROP_FOREIGNS | self::ALTER_FOREIGNS | self::CREATE_FOREIGNS;

    //Column modification behaviours
    const DROP_COLUMNS   = 0b000001000;
    const CREATE_COLUMNS = 0b000010000;
    const ALTER_COLUMNS  = 0b000100000;

    //All columns related operations
    const DO_COLUMNS = self::DROP_COLUMNS | self::ALTER_COLUMNS | self::CREATE_COLUMNS;

    //Index modification behaviours
    const DROP_INDEXES   = 0b001000000;
    const CREATE_INDEXES = 0b010000000;
    const ALTER_INDEXES  = 0b100000000;

    //All index related operations
    const DO_INDEXES = self::DROP_INDEXES | self::ALTER_INDEXES | self::CREATE_INDEXES;

    //General purpose schema operations
    const DO_RENAME = 0b10000000000;
    const DO_DROP   = 0b01000000000;

    //All operations
    const DO_ALL = self::DO_FOREIGNS | self::DO_INDEXES | self::DO_COLUMNS | self::DO_DROP | self::DO_RENAME;

    /**
     * @var LoggerInterface|null
     */
    private $logger = null;

    /**
     * @var Driver
     */
    protected $driver;

    /**
     * @param Driver               $driver
     * @param LoggerInterface|null $logger
     */
    public function __construct(Driver $driver, LoggerInterface $logger = null)
    {
        $this->driver = $driver;
        $this->logger = $logger;
    }

    /**
     * Associated driver.
     *
     * @return Driver
     */
    public function getDriver(): Driver
    {
        return $this->driver;
    }

    /**
     * Create table based on a given schema.
     *
     * @param AbstractTable $table
     *
     * @throws SchemaHandlerException
     */
    public function createTable(AbstractTable $table)
    {
        $this->log("Creating new table '{table}'.", ['table' => $table->getName()]);

        //Executing!
        $this->run($this->createStatement($table));

        //Not all databases support adding index while table creation, so we can do it after
        foreach ($table->getIndexes() as $index) {
            $this->createIndex($table, $index);
        }
    }

    /**
     * Drop table from database.
     *
     * @param AbstractTable $table
     *
     * @throws SchemaHandlerException
     */
    public function dropTable(AbstractTable $table)
    {
        $this->log("Dropping table '{table}'.", ['table' => $table->getName()]);
        $this->run("DROP TABLE {$this->identify($table->getInitialName())}");
    }

    /**
     * Sync given table schema.
     *
     * @param AbstractTable $table
     * @param int           $behaviour See behaviour constants.
     */
    public function syncTable(AbstractTable $table, int $behaviour = self::DO_ALL)
    {
        $comparator = $table->getComparator();

        if ($comparator->isPrimaryChanged()) {
            throw new DBALException("Unable to change primary keys for existed table");
        }

        if ($comparator->isRenamed() && $behaviour & self::DO_RENAME) {
            $this->log('Renaming table {table} to {name}.', [
                'table' => $this->identify($table->getInitialName()),
                'name'  => $this->identify($table->getName())
            ]);

            //Executing renaming
            $this->renameTable($table->getInitialName(), $table->getName());
        }

        /*
         * This is schema synchronization code, if you are reading it you are either experiencing
         * VERY weird bug, or you are very curious. Please contact me in a any scenario :)
         */
        $this->executeChanges($table, $behaviour, $comparator);
    }

    /**
     * Rename table from one name to another.
     *
     * @param string $table
     * @param string $name
     *
     * @throws SchemaHandlerException
     */
    public function renameTable(string $table, string $name)
    {
        $this->run("ALTER TABLE {$this->identify($table)} RENAME TO {$this->identify($name)}");
    }

    /**
     * Driver specific column add command.
     *
     * @param AbstractTable  $table
     * @param AbstractColumn $column
     *
     * @throws SchemaHandlerException
     */
    public function createColumn(AbstractTable $table, AbstractColumn $column)
    {
        $this->run("ALTER TABLE {$this->identify($table)} ADD COLUMN {$column->sqlStatement($this->driver)}");
    }

    /**
     * Driver specific column remove (drop) command.
     *
     * @param AbstractTable  $table
     * @param AbstractColumn $column
     *
     * @return AbstractHandler
     */
    public function dropColumn(AbstractTable $table, AbstractColumn $column)
    {
        foreach ($column->getConstraints() as $constraint) {
            //We have to erase all associated constraints
            $this->dropConstrain($table, $constraint);
        }

        $this->run("ALTER TABLE {$this->identify($table)} DROP COLUMN {$this->identify($column)}");
    }

    /**
     * Driver specific column alter command.
     *
     * @param AbstractTable  $table
     * @param AbstractColumn $initial
     * @param AbstractColumn $column
     *
     * @throws SchemaHandlerException
     */
    abstract public function alterColumn(
        AbstractTable $table,
        AbstractColumn $initial,
        AbstractColumn $column
    );

    /**
     * Driver specific index adding command.
     *
     * @param AbstractTable $table
     * @param AbstractIndex $index
     *
     * @throws SchemaHandlerException
     */
    public function createIndex(AbstractTable $table, AbstractIndex $index)
    {
        $this->run("CREATE {$index->sqlStatement($this->driver)}");
    }

    /**
     * Driver specific index remove (drop) command.
     *
     * @param AbstractTable $table
     * @param AbstractIndex $index
     *
     * @throws SchemaHandlerException
     */
    public function dropIndex(AbstractTable $table, AbstractIndex $index)
    {
        $this->run("DROP INDEX {$this->identify($index)}");
    }

    /**
     * Driver specific index alter command, by default it will remove and add index.
     *
     * @param AbstractTable $table
     * @param AbstractIndex $initial
     * @param AbstractIndex $index
     *
     * @throws SchemaHandlerException
     */
    public function alterIndex(AbstractTable $table, AbstractIndex $initial, AbstractIndex $index)
    {
        $this->dropIndex($table, $initial);
        $this->createIndex($table, $index);
    }

    /**
     * Driver specific foreign key adding command.
     *
     * @param AbstractTable     $table
     * @param AbstractReference $foreign
     *
     * @throws SchemaHandlerException
     */
    public function createForeign(AbstractTable $table, AbstractReference $foreign)
    {
        $this->run("ALTER TABLE {$this->identify($table)} ADD {$foreign->sqlStatement($this->driver)}");
    }

    /**
     * Driver specific foreign key remove (drop) command.
     *
     * @param AbstractTable     $table
     * @param AbstractReference $foreign
     *
     * @throws SchemaHandlerException
     */
    public function dropForeign(AbstractTable $table, AbstractReference $foreign)
    {
        $this->dropConstrain($table, $foreign->getName());
    }

    /**
     * Driver specific foreign key alter command, by default it will remove and add foreign key.
     *
     * @param AbstractTable     $table
     * @param AbstractReference $initial
     * @param AbstractReference $foreign
     *
     * @throws SchemaHandlerException
     */
    public function alterForeign(
        AbstractTable $table,
        AbstractReference $initial,
        AbstractReference $foreign
    ) {
        $this->dropForeign($table, $initial);
        $this->createForeign($table, $foreign);
    }

    /**
     * Drop column constraint using it's name.
     *
     * @param AbstractTable $table
     * @param string        $constraint
     *
     * @throws SchemaHandlerException
     */
    public function dropConstrain(AbstractTable $table, $constraint)
    {
        $this->run("ALTER TABLE {$this->identify($table)} DROP CONSTRAINT {$this->identify($constraint)}");
    }

    /**
     * Get statement needed to create table. Indexes will be created separately.
     *
     * @param AbstractTable $table
     *
     * @return string
     */
    protected function createStatement(AbstractTable $table)
    {
        $statement = ["CREATE TABLE {$this->identify($table)} ("];
        $innerStatement = [];

        //Columns
        foreach ($table->getColumns() as $column) {
            $this->assertValid($column);
            $innerStatement[] = $column->sqlStatement($this->driver);
        }

        //Primary key
        if (!empty($table->getPrimaryKeys())) {
            $primaryKeys = array_map([$this, 'identify'], $table->getPrimaryKeys());

            $innerStatement[] = 'PRIMARY KEY (' . join(', ', $primaryKeys) . ')';
        }

        //Constraints and foreign keys
        foreach ($table->getForeigns() as $reference) {
            $innerStatement[] = $reference->sqlStatement($this->driver);
        }

        $statement[] = "    " . join(",\n    ", $innerStatement);
        $statement[] = ')';

        return join("\n", $statement);
    }

    /**
     * @param AbstractTable   $table
     * @param int             $behaviour
     * @param StateComparator $comparator
     */
    protected function executeChanges(
        AbstractTable $table,
        int $behaviour,
        StateComparator $comparator
    ) {
        //Remove all non needed table constraints
        $this->dropConstrains($table, $behaviour, $comparator);

        if ($behaviour & self::CREATE_COLUMNS) {
            //After drops and before creations we can add new columns
            $this->createColumns($table, $comparator);
        }

        if ($behaviour & self::ALTER_COLUMNS) {
            //We can alter columns now
            $this->alterColumns($table, $comparator);
        }

        //Add new constrains and modify existed one
        $this->setConstrains($table, $behaviour, $comparator);
    }

    /**
     * Execute statement.
     *
     * @param string $statement
     * @param array  $parameters
     *
     * @return \PDOStatement
     *
     * @throws SchemaHandlerException
     */
    protected function run(string $statement, array $parameters = []): \PDOStatement
    {
        try {
            return $this->driver->statement($statement, $parameters);
        } catch (QueryException $e) {
            throw new SchemaHandlerException($e);
        }
    }

    /**
     * Helper function, saves log message into logger if any attached.
     *
     * @param string $message
     * @param array  $context
     */
    protected function log(string $message, array $context = [])
    {
        if (!empty($this->logger)) {
            $this->logger->debug($message, $context);
        }
    }

    /**
     * Create element identifier.
     *
     * @param AbstractElement|AbstractTable|string $element
     *
     * @return string
     */
    protected function identify($element)
    {
        if (is_string($element)) {
            return $this->driver->identifier($element);
        }

        if (!$element instanceof AbstractElement && !$element instanceof AbstractTable) {
            throw new InvalidArgumentException("Invalid argument type");
        }

        return $this->driver->identifier($element->getName());
    }

    /**
     * @param AbstractTable   $table
     * @param StateComparator $comparator
     */
    protected function alterForeigns(AbstractTable $table, StateComparator $comparator)
    {
        foreach ($comparator->alteredForeigns() as $pair) {
            /**
             * @var AbstractReference $initial
             * @var AbstractReference $current
             */
            list($current, $initial) = $pair;

            $this->log('Altering foreign key [{statement}] to [{new}] in {table}.', [
                'statement' => $initial->sqlStatement($this->driver),
                'table'     => $this->identify($table),
            ]);

            $this->alterForeign($table, $initial, $current);
        }
    }

    /**
     * @param AbstractTable   $table
     * @param StateComparator $comparator
     */
    protected function createForeigns(AbstractTable $table, StateComparator $comparator)
    {
        foreach ($comparator->addedForeigns() as $foreign) {
            $this->log('Adding foreign key [{statement}] into table {table}.', [
                'statement' => $foreign->sqlStatement($this->driver),
                'table'     => $this->identify($table),
            ]);

            $this->createForeign($table, $foreign);
        }
    }

    /**
     * @param AbstractTable   $table
     * @param StateComparator $comparator
     */
    protected function alterIndexes(AbstractTable $table, StateComparator $comparator)
    {
        foreach ($comparator->alteredIndexes() as $pair) {
            /**
             * @var AbstractIndex $initial
             * @var AbstractIndex $current
             */
            list($current, $initial) = $pair;

            $this->log('Altering index [{statement}] to [{new}] in table {table}.', [
                'statement' => $initial->sqlStatement($this->driver),
                'new'       => $current->sqlStatement($this->driver),
                'table'     => $this->identify($table),
            ]);

            $this->alterIndex($table, $initial, $current);
        }
    }

    /**
     * @param AbstractTable   $table
     * @param StateComparator $comparator
     */
    protected function createIndexes(AbstractTable $table, StateComparator $comparator)
    {
        foreach ($comparator->addedIndexes() as $index) {
            $this->log('Adding index [{statement}] into table {table}.', [
                'statement' => $index->sqlStatement($this->driver),
                'table'     => $this->identify($table),
            ]);

            $this->createIndex($table, $index);
        }
    }

    /**
     * @param AbstractTable   $table
     * @param StateComparator $comparator
     */
    protected function alterColumns(AbstractTable $table, StateComparator $comparator)
    {
        foreach ($comparator->alteredColumns() as $pair) {
            /**
             * @var AbstractColumn $initial
             * @var AbstractColumn $current
             */
            list($current, $initial) = $pair;

            $this->log('Altering column [{statement}] to [{new}] in table {table}.', [
                'statement' => $initial->sqlStatement($this->driver),
                'new'       => $current->sqlStatement($this->driver),
                'table'     => $this->identify($table),
            ]);

            $this->assertValid($current);
            $this->alterColumn($table, $initial, $current);
        }
    }

    /**
     * @param AbstractTable   $table
     * @param StateComparator $comparator
     */
    protected function createColumns(AbstractTable $table, StateComparator $comparator)
    {
        foreach ($comparator->addedColumns() as $column) {
            $this->log('Adding column [{statement}] into table {table}.', [
                'statement' => $column->sqlStatement($this->driver),
                'table'     => $this->identify($table),
            ]);

            $this->assertValid($column);
            $this->createColumn($table, $column);
        }
    }

    /**
     * @param AbstractTable   $table
     * @param StateComparator $comparator
     */
    protected function dropColumns(AbstractTable $table, StateComparator $comparator)
    {
        foreach ($comparator->droppedColumns() as $column) {
            $this->log('Dropping column [{statement}] from table {table}.', [
                'statement' => $column->sqlStatement($this->driver),
                'table'     => $this->identify($table),
            ]);

            $this->dropColumn($table, $column);
        }
    }

    /**
     * @param AbstractTable   $table
     * @param StateComparator $comparator
     */
    protected function dropIndexes(AbstractTable $table, StateComparator $comparator)
    {
        foreach ($comparator->droppedIndexes() as $index) {
            $this->log('Dropping index [{statement}] from table {table}.', [
                'statement' => $index->sqlStatement($this->driver),
                'table'     => $this->identify($table),
            ]);

            $this->dropIndex($table, $index);
        }
    }

    /**
     * @param AbstractTable   $table
     * @param StateComparator $comparator
     */
    protected function dropForeigns(AbstractTable $table, $comparator)
    {
        foreach ($comparator->droppedForeigns() as $foreign) {
            $this->log('Dropping foreign key [{statement}] from table {table}.', [
                'statement' => $foreign->sqlStatement($this->driver),
                'table'     => $this->identify($table),
            ]);

            $this->dropForeign($table, $foreign);
        }
    }

    /**
     * Applied to every column in order to make sure that driver support it.
     *
     * @param AbstractColumn $column
     *
     * @throws DriverException
     */
    protected function assertValid(AbstractColumn $column)
    {
        //All valid by default
    }

    /**
     * @param AbstractTable   $table
     * @param int             $behaviour
     * @param StateComparator $comparator
     */
    protected function dropConstrains(
        AbstractTable $table,
        int $behaviour,
        StateComparator $comparator
    ) {
        if ($behaviour & self::DROP_FOREIGNS) {
            $this->dropForeigns($table, $comparator);
        }

        if ($behaviour & self::DROP_INDEXES) {
            $this->dropIndexes($table, $comparator);
        }

        if ($behaviour & self::DROP_COLUMNS) {
            $this->dropColumns($table, $comparator);
        }
    }

    /**
     * @param AbstractTable   $table
     * @param int             $behaviour
     * @param StateComparator $comparator
     */
    protected function setConstrains(
        AbstractTable $table,
        int $behaviour,
        StateComparator $comparator
    ) {
        if ($behaviour & self::CREATE_INDEXES) {
            $this->createIndexes($table, $comparator);
        }

        if ($behaviour & self::ALTER_INDEXES) {
            $this->alterIndexes($table, $comparator);
        }

        if ($behaviour & self::CREATE_FOREIGNS) {
            $this->createForeigns($table, $comparator);
        }

        if ($behaviour & self::ALTER_FOREIGNS) {
            $this->alterForeigns($table, $comparator);
        }
    }
}