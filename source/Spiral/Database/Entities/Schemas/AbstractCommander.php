<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Database\Entities\Schemas;

use Spiral\Database\Entities\Driver;

/**
 * Holds set of DBMS specific element operations.
 */
abstract class AbstractCommander
{
    /**
     * @var Driver
     */
    private $driver = null;

    /**
     * @param Driver $driver
     */
    public function __construct(Driver $driver)
    {
        $this->driver = $driver;
    }

    /**
     * Associated driver.
     *
     * @return Driver
     */
    public function driver()
    {
        return $this->driver;
    }

    /**
     * Create table!
     *
     * @param AbstractTable $table
     * @return self
     */
    public function createTable(AbstractTable $table)
    {
        //Executing!
        $this->run($this->createStatement($table));

        //Not all databases support adding index while table creation, so we can do it after
        foreach ($table->getIndexes() as $index) {
            $this->addIndex($table, $index);
        }

        return $this;
    }

    /**
     * Drop table from database.
     *
     * @param string $table
     */
    public function dropTable($table)
    {
        $this->run("DROP TABLE {$this->quote($table)}");
    }

    /**
     * Rename table from one name to another.
     *
     * @param string $table
     * @param string $name
     * @return self
     */
    public function renameTable($table, $name)
    {
        $this->run("ALTER TABLE {$this->quote($table)} RENAME TO {$this->quote($name)}");

        return $this;
    }

    /**
     * Driver specific column add command.
     *
     * @param AbstractTable  $table
     * @param AbstractColumn $column
     * @return self
     */
    public function addColumn(AbstractTable $table, AbstractColumn $column)
    {
        $this->run("ALTER TABLE {$table->getName(true)} ADD COLUMN {$column->sqlStatement()}");

        return $this;
    }

    /**
     * Driver specific column remove (drop) command.
     *
     * @param AbstractTable  $table
     * @param AbstractColumn $column
     * @return self
     */
    public function dropColumn(AbstractTable $table, AbstractColumn $column)
    {
        foreach ($column->getConstraints() as $constraint) {
            //We have to erase all associated constraints
            $this->dropConstrain($table, $constraint);
        }

        $this->run("ALTER TABLE {$table->getName(true)} DROP COLUMN {$column->getName(true)}");

        return $this;
    }

    /**
     * Driver specific column alter command.
     *
     * @param AbstractTable  $table
     * @param AbstractColumn $initial
     * @param AbstractColumn $column
     * @return self
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
     * @return self
     */
    public function addIndex(AbstractTable $table, AbstractIndex $index)
    {
        $this->run("CREATE {$index->sqlStatement()}");

        return $this;
    }

    /**
     * Driver specific index remove (drop) command.
     *
     * @param AbstractTable $table
     * @param AbstractIndex $index
     * @return self
     */
    public function dropIndex(AbstractTable $table, AbstractIndex $index)
    {
        $this->run("DROP INDEX {$index->getName(true)}");

        return $this;
    }

    /**
     * Driver specific index alter command, by default it will remove and add index.
     *
     * @param AbstractTable $table
     * @param AbstractIndex $initial
     * @param AbstractIndex $index
     * @return self
     */
    public function alterIndex(AbstractTable $table, AbstractIndex $initial, AbstractIndex $index)
    {
        return $this->dropIndex($table, $initial)->addIndex($table, $index);
    }

    /**
     * Driver specific foreign key adding command.
     *
     * @param AbstractTable     $table
     * @param AbstractReference $foreign
     * @return self
     */
    public function addForeign(AbstractTable $table, AbstractReference $foreign)
    {
        $this->run("ALTER TABLE {$table->getName(true)} ADD {$foreign->sqlStatement()}");

        return $this;
    }

    /**
     * Driver specific foreign key remove (drop) command.
     *
     * @param AbstractTable     $table
     * @param AbstractReference $foreign
     * @return self
     */
    public function dropForeign(AbstractTable $table, AbstractReference $foreign)
    {
        return $this->dropConstrain($table, $foreign->getName());
    }

    /**
     * Driver specific foreign key alter command, by default it will remove and add foreign key.
     *
     * @param AbstractTable     $table
     * @param AbstractReference $initial
     * @param AbstractReference $foreign
     * @return self
     */
    public function alterForeign(
        AbstractTable $table,
        AbstractReference $initial,
        AbstractReference $foreign
    ) {
        return $this->dropForeign($table, $initial)->addForeign($table, $foreign);
    }

    /**
     * Drop column constraint using it's name.
     *
     * @param AbstractTable $table
     * @param string        $constraint
     * @return self
     */
    public function dropConstrain(AbstractTable $table, $constraint)
    {
        $this->run("ALTER TABLE {$table->getName(true)} DROP CONSTRAINT {$this->quote($constraint)}");

        return $this;
    }

    /**
     * Execute statement.
     *
     * @param string $statement
     * @param array  $parameters
     * @return \PDOStatement
     */
    protected function run($statement, array $parameters = [])
    {
        return $this->driver->statement($statement, $parameters);
    }

    /**
     * Quote identifier.
     *
     * @param string $identifier
     * @return string
     */
    protected function quote($identifier)
    {
        return $this->driver->identifier($identifier);
    }

    /**
     * Get statement needed to create table.
     *
     * @param AbstractTable $table
     * @return string
     */
    protected function createStatement(AbstractTable $table)
    {
        $statement = ["CREATE TABLE {$table->getName(true)} ("];
        $innerStatement = [];

        //Columns
        foreach ($table->getColumns() as $column) {
            $innerStatement[] = $column->sqlStatement();
        }

        //Primary key
        if (!empty($table->getPrimaryKeys())) {
            $primaryKeys = array_map([$this, 'quote'], $table->getPrimaryKeys());

            $innerStatement[] = 'PRIMARY KEY (' . join(', ', $primaryKeys) . ')';
        }

        //Constraints and foreign keys
        foreach ($table->getForeigns() as $reference) {
            $innerStatement[] = $reference->sqlStatement();
        }

        $statement[] = "    " . join(",\n    ", $innerStatement);
        $statement[] = ')';

        return join("\n", $statement);
    }
}