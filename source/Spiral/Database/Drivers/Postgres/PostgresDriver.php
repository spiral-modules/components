<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Database\Drivers\Postgres;

use Spiral\Core\ContainerInterface;
use Spiral\Core\HippocampusInterface;
use Spiral\Database\DatabaseInterface;
use Spiral\Database\Drivers\Postgres\Schemas\ColumnSchema;
use Spiral\Database\Drivers\Postgres\Schemas\IndexSchema;
use Spiral\Database\Drivers\Postgres\Schemas\ReferenceSchema;
use Spiral\Database\Drivers\Postgres\Schemas\TableSchema;
use Spiral\Database\Entities\Database;
use Spiral\Database\Entities\Driver;
use Spiral\Database\Exceptions\DriverException;
use Spiral\Database\Injections\Parameter;

/**
 * Talks to postgres databases.
 */
class PostgresDriver extends Driver
{
    /**
     * Driver type.
     */
    const TYPE = DatabaseInterface::POSTGRES;

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
    private $primaryKeys = [];

    /**
     * @invisible
     * @var HippocampusInterface
     */
    protected $memory = null;

    /**
     * {@inheritdoc}
     * @param ContainerInterface   $container
     * @param string               $name
     * @param array                $config
     * @param HippocampusInterface $memory
     */
    public function __construct(
        ContainerInterface $container,
        $name,
        array $config,
        HippocampusInterface $memory = null
    ) {
        parent::__construct($container, $name, $config);
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
        foreach ($this->query($query) as $row) {
            $tables[] = $row['table_name'];
        }

        return $tables;
    }

    /**
     * Get singular primary key associated with desired table. Used to emulate last insert id.
     *
     * @param string $table Fully specified table name, including postfix.
     * @return string
     * @throws DriverException
     */
    public function getPrimary($table)
    {
        if (!empty($this->memory) && empty($this->primaryKeys)) {
            $this->primaryKeys = $this->memory->loadData($this->getSource() . '-primary');
        }

        if (!empty($this->primaryKeys) && array_key_exists($table, $this->primaryKeys)) {
            return $this->primaryKeys[$table];
        }

        if (!$this->hasTable($table)) {
            throw new DriverException(
                "Unable to fetch table primary key, no such table '{$table}' exists."
            );
        }

        $this->primaryKeys[$table] = $this->tableSchema($table)->getPrimaryKeys();
        if (count($this->primaryKeys[$table]) === 1) {
            //We do support only single primary key
            $this->primaryKeys[$table] = $this->primaryKeys[$table][0];
        } else {
            $this->primaryKeys[$table] = null;
        }

        //Caching
        if (!empty($this->memory)) {
            $this->memory->saveData($this->getSource() . '-primary', $this->primaryKeys);
        }

        return $this->primaryKeys[$table];
    }

    /**
     * {@inheritdoc}
     */
    public function insertBuilder(Database $database, array $parameters = [])
    {
        return $this->container->construct(InsertQuery::class, [
                'database' => $database,
                'compiler' => $this->queryCompiler($database->getPrefix())
            ] + $parameters);
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
