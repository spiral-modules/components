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
use Spiral\Database\Drivers\MySQL\Schemas\Commander;
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
    const SCHEMA_TABLE = TableSchema::class;

    /**
     * Commander used to execute commands. :).
     */
    const COMMANDER = Commander::class;

    /**
     * Query compiler class.
     */
    const QUERY_COMPILER = QueryCompiler::class;

    /**
     * Default timestamp expression.
     */
    const TIMESTAMP_NOW = 'CURRENT_TIMESTAMP';

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
    public function identifier($identifier)
    {
        return $identifier == '*' ? '*' : '`' . str_replace('`', '``', $identifier) . '`';
    }

    /**
     * {@inheritdoc}
     */
    public function hasTable($name)
    {
        $query = 'SELECT COUNT(*) FROM `information_schema`.`tables` WHERE `table_schema` = ? AND `table_name` = ?';

        return (bool)$this->query($query, [$this->getSource(), $name])->fetchColumn();
    }

    /**
     * {@inheritdoc}
     */
    public function tableNames()
    {
        $result = [];
        foreach ($this->query('SHOW TABLES')->fetchMode(PDO::FETCH_NUM) as $row) {
            $result[] = $row[0];
        }

        return $result;
    }
}
