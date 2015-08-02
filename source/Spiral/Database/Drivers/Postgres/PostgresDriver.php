<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Drivers\Postgres;

use Spiral\Core\ContainerInterface;
use Spiral\Core\HippocampusInterface;
use Spiral\Database\Entities\Driver;

/**
 * Talks to postgres databases.
 */
class PostgresDriver extends Driver
{
    /**
     * Driver name, for convenience.
     */
    const NAME = 'PostgresSQL';

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
    const TIMESTAMP_NOW = 'now()';

    /**
     * Cached list of primary keys associated with their table names. Used by InsertBuilder to
     * emulate last insert id.
     *
     * @var array
     */
    protected $primaryKeys = [];

    /**
     * @invisible
     * @var HippocampusInterface
     */
    protected $memory = null;

    /**
     * {@inheritdoc}
     *
     * @param HippocampusInterface $memory
     */
    public function __construct(
        ContainerInterface $container,
        HippocampusInterface $memory,
        array $config
    )
    {
        parent::__construct($container, $config);
        $this->memory = $memory;
    }

    /**
     * {@inheritdoc}
     */
    public function hasTable($name)
    {
        $query = 'SELECT "table_name" FROM "information_schema"."tables" '
            . 'WHERE "table_schema" = \'public\' AND "table_type" = \'BASE TABLE\' '
            . 'AND "table_name" = ?';

        return (bool)$this->query($query, [$name])->fetchColumn();
    }

    /**
     * {@inheritdoc}
     */
    public function tableNames()
    {
        $query = 'SELECT "table_name" FROM "information_schema"."tables" '
            . 'WHERE "table_schema" = \'public\' AND "table_type" = \'BASE TABLE\'';

        $tables = [];
        foreach ($this->query($query) as $row)
        {
            $tables[] = $row['table_name'];
        }

        return $tables;
    }

    /**
     * {@inheritdoc}
     */
    public function prepareParameters(array $parameters)
    {
        $result = parent::prepareParameters($parameters);

        array_walk_recursive($result, function (&$value)
        {
            if (is_bool($value))
            {
                //PDO casts boolean as string, Postgres can't understand it, let's cast it as int
                $value = (int)$value;
            }
        });

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function createPDO()
    {
        //Spiral is purely UTF-8
        $pdo = parent::createPDO();
        $pdo->exec("SET NAMES 'UTF-8'");

        return $pdo;
    }
}