<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Database\Drivers\SQLite;

use Spiral\Database\DatabaseInterface;
use Spiral\Database\Drivers\SQLite\Schemas\ColumnSchema;
use Spiral\Database\Drivers\SQLite\Schemas\IndexSchema;
use Spiral\Database\Drivers\SQLite\Schemas\ReferenceSchema;
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
    const SCHEMA_TABLE     = TableSchema::class;
    const SCHEMA_COLUMN    = ColumnSchema::class;
    const SCHEMA_INDEX     = IndexSchema::class;
    const SCHEMA_REFERENCE = ReferenceSchema::class;

    /**
     * Query compiler class.
     */
    const QUERY_COMPILER = QueryCompiler::class;

    /**
     * Default timestamp expression.
     */
    const TIMESTAMP_NOW = 'CURRENT_TIMESTAMP';

    /**
     * Get driver source database or file name.
     *
     * @return string
     * @throws DriverException
     */
    public function getSource()
    {
        //Remove "sqlite:"
        return substr($this->config['connection'], 7);
    }

    /**
     * {@inheritdoc}
     */
    public function hasTable($name)
    {
        $query = 'SELECT sql FROM sqlite_master WHERE type = \'table\' and name = ?';

        return (bool)$this->query($query, [$name])->fetchColumn();
    }

    /**
     * {@inheritdoc}
     */
    public function truncate($table)
    {
        $this->statement("DELETE FROM {$this->identifier($table)}");
    }

    /**
     * {@inheritdoc}
     */
    public function tableNames()
    {
        $tables = [];
        foreach ($this->query("SELECT * FROM sqlite_master WHERE type = 'table'") as $table) {
            if ($table['name'] != 'sqlite_sequence') {
                $tables[] = $table['name'];
            }
        }

        return $tables;
    }

    /**
     * {@inheritdoc}
     */
    protected function isolationLevel($level)
    {
        $this->logger()->error(
            "Transaction isolation level is not fully supported by SQLite ({level}).",
            compact('level')
        );
    }
}