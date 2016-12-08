<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Drivers\SQLite;

use Spiral\Database\DatabaseInterface;
use Spiral\Database\Drivers\SQLite\Schemas\TableSchema;
use Spiral\Database\Entities\Driver;
use Spiral\Database\Exceptions\DriverException;

/**
 * Talks to sqlite databases.
 */
class SQLiteDriver extends Driver
{
    /**
     * Driver type.
     */
    const TYPE = DatabaseInterface::SQLITE;

    /**
     * Driver schemas.
     */
    const TABLE_SCHEMA_CLASS = TableSchema::class;

    /**
     * Commander used to execute commands. :).
     */
    // const COMMANDER = Commander::class;

    /**
     * Query compiler class.
     */
    const QUERY_COMPILER = QueryCompiler::class;

    /**
     * Get driver source database or file name.
     *
     * @return string
     *
     * @throws DriverException
     */
    public function getSource(): string
    {
        //Remove "sqlite:"
        return substr($this->defaultOptions['connection'], 7);
    }

    /**
     * {@inheritdoc}
     */
    public function hasTable(string $name): bool
    {
        $query = "SELECT 'sql' FROM sqlite_master WHERE type = 'table' and name = ?";

        return (bool)$this->query($query, [$name])->fetchColumn();
    }

    /**
     * {@inheritdoc}
     */
    public function truncateData(string $table)
    {
        $this->statement("DELETE FROM {$this->identifier($table)}");
    }

    /**
     * {@inheritdoc}
     */
    public function tableNames(): array
    {
        $tables = [];
        foreach ($this->query("SELECT * FROM 'sqlite_master' WHERE type = 'table'") as $table) {
            if ($table['name'] != 'sqlite_sequence') {
                $tables[] = $table['name'];
            }
        }

        return $tables;
    }

    /**
     * {@inheritdoc}
     */
    protected function isolationLevel(string $level)
    {
        if ($this->isProfiling()) {
            $this->logger()->alert(
                'Transaction isolation level is not fully supported by SQLite ({level}).',
                compact('level')
            );
        }
    }
}
