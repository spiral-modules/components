<?php
/**
 * Spiral Framework, Core Components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Database\Drivers\MySQL;

use Spiral\Database\Drivers\MySQL\Schemas\MySQLTable;
use Spiral\Database\Entities\AbstractHandler;
use Spiral\Database\Exceptions\SchemaException;
use Spiral\Database\Schemas\Prototypes\AbstractColumn;
use Spiral\Database\Schemas\Prototypes\AbstractIndex;
use Spiral\Database\Schemas\Prototypes\AbstractReference;
use Spiral\Database\Schemas\Prototypes\AbstractTable;

class MySQLHandler extends AbstractHandler
{
    /**
     * {@inheritdoc}
     */
    public function alterColumn(
        AbstractTable $table,
        AbstractColumn $initial,
        AbstractColumn $column
    ) {
        $this->run("ALTER TABLE {$this->identify($table)} CHANGE {$this->identify($initial)} {$column->sqlStatement($this->driver)}");
    }

    /**
     * {@inheritdoc}
     */
    public function dropIndex(AbstractTable $table, AbstractIndex $index)
    {
        $this->run("DROP INDEX {$this->identify($index)} ON {$this->identify($table)}");

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function alterIndex(AbstractTable $table, AbstractIndex $initial, AbstractIndex $index)
    {
        $this->run("ALTER TABLE {$this->identify($table)} DROP INDEX  {$this->identify($index)}, ADD {$index->sqlStatement($this->driver, false)}");
    }

    /**
     * {@inheritdoc}
     */
    public function dropForeign(AbstractTable $table, AbstractReference $foreign)
    {
        $this->run("ALTER TABLE {$this->identify($table)} DROP FOREIGN KEY {$this->identify($foreign)}");
    }

    /**
     * Get statement needed to create table.
     *
     * @param AbstractTable $table
     *
     * @return string
     *
     * @throws SchemaException
     */
    protected function createStatement(AbstractTable $table)
    {
        if (!$table instanceof MySQLTable) {
            throw new SchemaException('MySQLHandler can process only MySQL tables');
        }

        return parent::createStatement($table) . " ENGINE {$table->getEngine()}";
    }
}