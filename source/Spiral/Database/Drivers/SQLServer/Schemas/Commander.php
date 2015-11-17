<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Database\Drivers\SQLServer\Schemas;

use Spiral\Database\Entities\Schemas\AbstractColumn;
use Spiral\Database\Entities\Schemas\AbstractCommander;
use Spiral\Database\Entities\Schemas\AbstractIndex;
use Spiral\Database\Entities\Schemas\AbstractTable;
use Spiral\Database\Exceptions\SchemaException;

/**
 * SQLServer commander.
 */
class Commander extends AbstractCommander
{
    /**
     * Rename table from one name to another.
     *
     * @param string $table
     * @param string $name
     * @return self
     */
    public function renameTable($table, $name)
    {
        $this->run("sp_rename @objname = ?, @newname = ?", [$table, $name]);

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
        $this->run("ALTER TABLE {$table->getName(true)} ADD {$column->sqlStatement()}");

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
    public function alterColumn(
        AbstractTable $table,
        AbstractColumn $initial,
        AbstractColumn $column
    ) {
        if (!$initial instanceof ColumnSchema || !$column instanceof ColumnSchema) {
            throw new SchemaException("SQlServer commander can work only with Postgres columns.");
        }

        if ($column->getName() != $initial->getName()) {
            //Renaming is separate operation
            $this->run("sp_rename ?, ?, 'COLUMN'", [
                $table->getName() . '.' . $initial->getName(),
                $column->getName()
            ]);
        }

        //In SQLServer we have to drop ALL related indexes and foreign keys while
        //applying type change... yeah...

        $indexesBackup = [];
        $foreignBackup = [];
        foreach ($table->getIndexes() as $index) {
            if (in_array($column->getName(), $index->getColumns())) {
                $indexesBackup[] = $index;
                $this->dropIndex($table, $index);
            }
        }

        foreach ($table->getForeigns() as $foreign) {
            if ($foreign->getColumn() == $column->getName()) {
                $foreignBackup[] = $foreign;
                $this->dropForeign($table, $foreign);
            }
        }

        //Column will recreate needed constraints
        foreach ($column->getConstraints() as $constraint) {
            $this->dropConstrain($table, $constraint);
        }

        foreach ($column->alteringOperations($initial) as $operation) {
            $this->run("ALTER TABLE {$table->getName(true)} {$operation}");
        }

        //Restoring indexes and foreign keys
        foreach ($indexesBackup as $index) {
            $this->addIndex($table, $index);
        }

        foreach ($foreignBackup as $foreign) {
            $this->addForeign($table, $foreign);
        }
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
        $this->run("DROP INDEX {$index->getName(true)} ON {$table->getName(true)}");

        return $this;
    }
}