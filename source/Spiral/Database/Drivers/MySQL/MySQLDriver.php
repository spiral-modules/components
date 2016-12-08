<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Drivers\MySQL;

use PDO;
use Spiral\Database\DatabaseInterface;
use Spiral\Database\Drivers\MySQL\Schemas\TableSchema;
use Spiral\Database\Entities\Driver;

/**
 * Talks to mysql databases.
 */
class MySQLDriver extends Driver
{
    /**
     * Driver type.
     */
    const TYPE = DatabaseInterface::MYSQL;

    /**
     * Driver schemas.
     */
    const TABLE_SCHEMA_CLASS = TableSchema::class;

    /**
     * Query compiler class.
     */
    const QUERY_COMPILER = QueryCompiler::class;

    /**
     * Default timestamp expression.
     */
    const DATETIME_NOW = 'CURRENT_TIMESTAMP';

    /**
     * {@inheritdoc}
     */
    protected $options = [
        PDO::ATTR_CASE               => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES "UTF8"',
    ];

    /**
     * {@inheritdoc}
     */
    public function identifier(string $identifier): string
    {
        return $identifier == '*' ? '*' : '`' . str_replace('`', '``', $identifier) . '`';
    }

    /**
     * {@inheritdoc}
     */
    public function hasTable(string $name): bool
    {
        $query = "SELECT COUNT(*) FROM `information_schema`.`tables` WHERE `table_schema` = ? AND `table_name` = ?";

        return (bool)$this->query($query, [$this->getSource(), $name])->fetchColumn();
    }

    /**
     * {@inheritdoc}
     */
    public function truncateData(string $table)
    {
        $this->statement("TRUNCATE TABLE {$this->identifier($table)}");
    }

    /**
     * {@inheritdoc}
     */
    public function tableNames(): array
    {
        $result = [];
        foreach ($this->query("SHOW TABLES")->fetch(PDO::FETCH_NUM) as $row) {
            $result[] = $row[0];
        }

        return $result;
    }
}
