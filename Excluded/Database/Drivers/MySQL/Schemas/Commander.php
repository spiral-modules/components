<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Drivers\MySQL\Schemas;

use Spiral\Database\Entities\Schemas\AbstractColumn;
use Spiral\Database\Entities\Schemas\AbstractCommander;
use Spiral\Database\Entities\Schemas\AbstractIndex;
use Spiral\Database\Entities\Schemas\AbstractReference;
use Spiral\Database\Entities\Schemas\AbstractTable;
use Spiral\Database\Exceptions\SchemaException;

/**
 * MySQL commander.
 */
class Commander extends AbstractCommander
{
    /**
     * {@inheritdoc}
     */
    public function alterColumn(
        AbstractTable $table,
        AbstractColumn $initial,
        AbstractColumn $column
    ) {
        $query = 'ALTER TABLE {table} CHANGE {column} {statement}';

        $query = \Spiral\interpolate($query, [
            'table'     => $table->getName(true),
            'column'    => $initial->getName(true),
            'statement' => $column->sqlStatement(),
        ]);

        $this->run($query);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function dropIndex(AbstractTable $table, AbstractIndex $index)
    {
        $this->run("DROP INDEX {$index->getName(true)} ON {$table->getName(true)}");

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function alterIndex(AbstractTable $table, AbstractIndex $initial, AbstractIndex $index)
    {
        $query = \Spiral\interpolate('ALTER TABLE {table} DROP INDEX {index}, ADD {statement}', [
            'table'     => $table->getName(true),
            'index'     => $initial->getName(true),
            'statement' => $index->sqlStatement(false),
        ]);

        $this->run($query);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function dropForeign(AbstractTable $table, AbstractReference $foreign)
    {
        $this->run("ALTER TABLE {$table->getName(true)} DROP FOREIGN KEY {$foreign->getName(true)}");

        return $this;
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
        if (!$table instanceof TableSchema) {
            throw new SchemaException('MySQL commander can process only MySQL tables');
        }

        return parent::createStatement($table) . " ENGINE {$table->getEngine()}";
    }
}
