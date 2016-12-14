<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Database\Drivers\SQLServer;

use Spiral\Database\Drivers\SQLServer\Schemas\SQLServerColumn;
use Spiral\Database\Entities\AbstractHandler;
use Spiral\Database\Exceptions\SchemaException;
use Spiral\Database\Schemas\Prototypes\AbstractColumn;
use Spiral\Database\Schemas\Prototypes\AbstractIndex;
use Spiral\Database\Schemas\Prototypes\AbstractTable;

class SQLServerHandler extends AbstractHandler
{
    /**
     * {@inheritdoc}
     */
    public function renameTable(string $table, string $name)
    {
        $this->run('sp_rename @objname = ?, @newname = ?', [$table, $name]);
    }

    /**
     * {@inheritdoc}
     */
    public function createColumn(AbstractTable $table, AbstractColumn $column)
    {
        $this->run("ALTER TABLE {$this->identify($table)} ADD {$column->sqlStatement($this->driver)}");
    }

    /**
     * Driver specific column alter command.
     *
     * @param AbstractTable  $table
     * @param AbstractColumn $initial
     * @param AbstractColumn $column
     *
     * @throws SchemaException
     */
    public function alterColumn(
        AbstractTable $table,
        AbstractColumn $initial,
        AbstractColumn $column
    ) {
        if (!$initial instanceof SQLServerColumn || !$column instanceof SQLServerColumn) {
            throw new SchemaException('SQlServer handler can work only with SQLServer columns.');
        }

//        if ($column->getName() != $initial->getName()) {
//            //Renaming is separate operation
//            $this->run("sp_rename ?, ?, 'COLUMN'", [
//                $table->getName() . '.' . $initial->getName(),
//                $column->getName(),
//            ]);
//        }
//
//        //In SQLServer we have to drop ALL related indexes and foreign keys while
//        //applying type change... yeah...
//
//        $indexesBackup = [];
//        $foreignBackup = [];
//        foreach ($table->getIndexes() as $index) {
//            if (in_array($column->getName(), $index->getColumns())) {
//                $indexesBackup[] = $index;
//                $this->dropIndex($table, $index);
//            }
//        }
//
//        foreach ($table->getForeigns() as $foreign) {
//            if ($foreign->getColumn() == $column->getName()) {
//                $foreignBackup[] = $foreign;
//                $this->dropForeign($table, $foreign);
//            }
//        }
//
//        //Column will recreate needed constraints
//        foreach ($column->getConstraints() as $constraint) {
//            $this->dropConstrain($table, $constraint);
//        }
//
//        foreach ($column->alteringOperations($initial) as $operation) {
//            $this->run("ALTER TABLE {$table->getName(true)} {$operation}");
//        }
//
//        //Restoring indexes and foreign keys
//        foreach ($indexesBackup as $index) {
//            $this->addIndex($table, $index);
//        }
//
//        foreach ($foreignBackup as $foreign) {
//            $this->addForeign($table, $foreign);
//        }
    }

    /**
     * Driver specific index remove (drop) command.
     *
     * @param AbstractTable $table
     * @param AbstractIndex $index
     *
     * @return self
     */
    public function dropIndex(AbstractTable $table, AbstractIndex $index)
    {
        $this->run("DROP INDEX {$index->getName(true)} ON {$table->getName(true)}");

        return $this;
    }
}