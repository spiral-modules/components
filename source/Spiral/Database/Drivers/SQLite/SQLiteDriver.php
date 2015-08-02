<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Drivers\SQLite;

use Spiral\Core\ContainerInterface;
use Spiral\Database\Entities\Driver;

/**
 * Talks to sqlite databases.
 */
class SQLiteDriver extends Driver
{
    /**
     * Driver name, for convenience.
     */
    const NAME = 'SQLite';

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
     * SQL query to fetch table names from database. Declared as constant only because i love well
     * organized things.
     *
     * @var string
     */
    const FETCH_TABLES_QUERY = "SELECT * FROM sqlite_master WHERE type = 'table'";

    /**
     * Query to check table existence.
     *
     * @var string
     */
    const TABLE_EXISTS_QUERY = "SELECT sql FROM sqlite_master WHERE type = 'table' and name = ?";

    /**
     * {@inheritdoc}
     */
    public function __construct(ContainerInterface $container, array $config)
    {
        parent::__construct($container, $config);

        //Remove "sqlite:"
        $this->databaseName = substr($this->config['connection'], 7);
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
    public function isolationLevel($level)
    {
        $this->logger()->error(
            "Transaction isolation level is not fully supported by SQLite ({level}).",
            compact('level')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function hasTable($name)
    {
        return (bool)$this->query(self::TABLE_EXISTS_QUERY, [$name])->fetchColumn();
    }

    /**
     * {@inheritdoc}
     */
    public function tableNames()
    {
        $tables = [];
        foreach ($this->query(static::FETCH_TABLES_QUERY) as $table)
        {
            if ($table['name'] != 'sqlite_sequence')
            {
                $tables[] = $table['name'];
            }
        }

        return $tables;
    }
}