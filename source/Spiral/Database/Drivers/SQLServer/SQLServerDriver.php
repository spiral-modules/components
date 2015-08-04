<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Drivers\SQLServer;

use Spiral\Database\DatabaseInterface;
use Spiral\Database\Drivers\SQLServer\Schemas\TableSchema;
use Spiral\Database\Drivers\SQLServer\Schemas\ColumnSchema;
use Spiral\Database\Drivers\SQLServer\Schemas\IndexSchema;
use Spiral\Database\Drivers\SQLServer\Schemas\ReferenceSchema;
use Spiral\Database\Entities\Driver;
use PDO;

/**
 * Talk to microsoft sql server databases.
 */
class SQLServerDriver extends Driver
{
    /**
     * Driver type.
     */
    const TYPE = DatabaseInterface::SQL_SERVER;

    /**
     * Driver schemas.
     */
    const SCHEMA_TABLE     = TableSchema::class;
    const SCHEMA_COLUMN    = ColumnSchema::class;
    const SCHEMA_INDEX     = IndexSchema::class;
    const SCHEMA_REFERENCE = ReferenceSchema::class;

    /**
     * Query result class.
     */
    const QUERY_RESULT = QueryResult::class;

    /**
     * Query compiler class.
     */
    const QUERY_COMPILER = QueryCompiler::class;

    /**
     * DateTime format to be used to perform automatic conversion of DateTime objects.
     *
     * @var string
     */
    const DATETIME = 'Y-m-d\TH:i:s.000';

    /**
     * Default datetime value.
     */
    const DEFAULT_DATETIME = '1970-01-01T00:00:00';

    /**
     * Default timestamp expression.
     */
    const TIMESTAMP_NOW = 'getdate()';

    /**
     * @var array
     */
    protected $options = [
        PDO::ATTR_CASE              => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_STRINGIFY_FETCHES => false
    ];

    /**
     * SQLServer version. Required for better LIMIT/OFFSET syntax.
     *
     * @link http://stackoverflow.com/questions/2135418/equivalent-of-limit-and-offset-for-sql-server
     * @var int
     */
    protected $serverVersion = 0;

    /**
     * {@inheritdoc}
     */
    public function identifier($identifier)
    {
        return $identifier == '*' ? '*' : '[' . str_replace('[', '[[', $identifier) . ']';
    }

    /**
     * {@inheritdoc}
     */
    public function hasTable($name)
    {
        $query = 'SELECT COUNT(*) FROM information_schema.tables '
            . 'WHERE table_type = \'BASE TABLE\' AND table_name = ?';

        return (bool)$this->query($query, [$name])->fetchColumn();
    }

    /**
     * {@inheritdoc}
     */
    public function tableNames()
    {
        $query = 'SELECT table_name FROM information_schema.tables WHERE table_type = \'BASE TABLE\'';

        $tables = [];
        foreach ($this->query($query)->fetchMode(PDO::FETCH_NUM) as $row)
        {
            $tables[] = $row[0];
        }

        return $tables;
    }

    /**
     * SQLServer version.
     *
     * @link http://stackoverflow.com/questions/2135418/equivalent-of-limit-and-offset-for-sql-server
     * @return int
     */
    public function getServerVersion()
    {
        if (empty($this->serverVersion))
        {
            $this->serverVersion = (int)$this->getPDO()->getAttribute(\PDO::ATTR_SERVER_VERSION);
        }

        return $this->serverVersion;
    }
}