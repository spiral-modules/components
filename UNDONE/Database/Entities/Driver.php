<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Entities;

use Psr\Log\LoggerAwareInterface;
use Spiral\Core\Component;
use Spiral\Database\Builders\DeleteQuery;
use Spiral\Database\Builders\InsertQuery;
use Spiral\Database\Builders\SelectQuery;
use Spiral\Database\Builders\UpdateQuery;
use Spiral\Database\Schemas\AbstractColumn;
use Spiral\Database\Schemas\AbstractIndex;
use Spiral\Database\Schemas\AbstractReference;
use Spiral\Database\Schemas\AbstractTable;
use PDO;
use PDOStatement;
use Spiral\Core\ContainerInterface;
use Spiral\Debug\Traits\BenchmarkTrait;
use Spiral\Debug\Traits\LoggerTrait;
use Spiral\Events\Traits\EventsTrait;

abstract class Driver extends Component implements LoggerAwareInterface
{
    /**
     * Profiling and logging.
     */
    use LoggerTrait, EventsTrait, BenchmarkTrait;

    /**
     * Driver schemas.
     */
    const SCHEMA_TABLE     = '';
    const SCHEMA_COLUMN    = '';
    const SCHEMA_INDEX     = '';
    const SCHEMA_REFERENCE = '';

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
    const DATETIME = 'Y-m-d H:i:s';

    /**
     * Default datetime value.
     */
    const DEFAULT_DATETIME = '1970-01-01 00:00:00';

    /**
     * Default timestamp expression.
     */
    const TIMESTAMP_NOW = 'DRIVER_SPECIFIC_NOW_EXPRESSION';

    /**
     * Current transaction level (count of nested transactions). Not all drives can support nested
     * transactions.
     *
     * @var int
     */
    private $transactionLevel = 0;

    /**
     * Database name (fetched from connection string). In some cases can contain empty string (SQLite).
     *
     * @var string
     */
    protected $databaseName = '';

    /**
     * Connection configuration described in DBAL config file. Any driver can be used as data source
     * for multiple databases as table prefix and quotation defined on Database instance level.
     *
     * @var array
     */
    protected $config = [
        'connection' => '',
        'username'   => '',
        'password'   => '',
        'profiling'  => true,
        'options'    => []
    ];

    /**
     * Default driver PDO options set, this keys will be merged with data provided by DBAL configuration.
     *
     * @var array
     */
    protected $options = [
        PDO::ATTR_CASE              => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_STRINGIFY_FETCHES => false
    ];

    /**
     * @var PDO
     */
    protected $pdo = null;

    /**
     * @var ContainerInterface
     */
    protected $container = null;

    /**
     * Driver instances responsible for all database low level operations which can be DBMS specific
     * - such as connection preparation, custom table/column/index/reference schemas and etc.
     *
     * @param ContainerInterface $container
     * @param array              $config
     */
    public function __construct(ContainerInterface $container, array $config)
    {
        $this->container = $container;

        $this->config = $config + $this->config;
        $this->options = $config['options'] + $this->options;

        if (preg_match('/(?:dbname|database)=([^;]+)/i', $this->config['connection'], $matches))
        {
            $this->databaseName = $matches[1];
        }
    }

    /**
     * Get Driver configuration data.
     *
     * @return array
     */
    public function config()
    {
        return $this->config;
    }

    /**
     * Get database name (fetched from connection string). In some cases can return empty string
     * (SQLite).
     *
     * @return string|null
     */
    public function getDatabaseName()
    {
        return $this->databaseName;
    }

    /**
     * While profiling enabled driver will create query logging and benchmarking events. This is
     * recommended option on development environment.
     *
     * @param bool $enabled Enable or disable driver profiling.
     * @return $this
     */
    public function profiling($enabled = true)
    {
        $this->config['profiling'] = $enabled;

        return $this;
    }

    /**
     * Check if profiling mode is enabled.
     *
     * @return bool
     */
    public function isProfiling()
    {
        return $this->config['profiling'];
    }

    /**
     * Force driver to connect.
     *
     * @return bool
     */
    public function connect()
    {
        $this->getPDO();

        return $this->isConnected();
    }

    /**
     * Disconnect PDO.
     *
     * @return $this
     */
    public function disconnect()
    {
        $this->fire('disconnect', $this->pdo);
        $this->pdo = null;

        return $this;
    }

    /**
     * Check if PDO already constructed and ready for use.
     *
     * @return bool
     */
    public function isConnected()
    {
        return (bool)$this->pdo;
    }

    /**
     * Manually set associated PDO instance.
     *
     * @param PDO $pdo
     * @return $this
     */
    public function setPDO(PDO $pdo)
    {
        $this->pdo = $pdo;

        return $this;
    }

    /**
     * Get associated PDO connection. Driver will automatically connect to PDO if it's not already
     * exists.
     *
     * @return PDO
     */
    public function getPDO()
    {
        if (!empty($this->pdo))
        {
            return $this->pdo;
        }

        $this->benchmark('connect', $this->config['connection']);
        $this->pdo = $this->fire('connect', $this->createPDO());
        $this->benchmark('connect', $this->config['connection']);

        return $this->pdo;
    }

    /**
     * Get prepared PDOStatement instance. Query will be run against connected PDO object.
     *
     * @param string $query              SQL statement with parameter placeholders.
     * @param array  $parameters         Parameters to be binded into query.
     * @param array  $preparedParameters Processed parameters will be saved into this array.
     * @return PDOStatement
     */
    public function statement($query, array $parameters = [], &$preparedParameters = null)
    {
        $preparedParameters = $parameters = $this->prepareParameters($parameters);

        try
        {
            if ($this->config['profiling'])
            {
                $builtQuery = QueryCompiler::interpolate($query, $parameters);
                $this->benchmark($this->databaseName, $builtQuery);
            }

            $pdoStatement = $this->getPDO()->prepare($query);

            //Configuring statement binded parameters
            $pdoStatement->execute($parameters);

            $this->fire('statement', [
                'statement'  => $pdoStatement,
                'query'      => $query,
                'parameters' => $parameters
            ]);

            if ($this->config['profiling'] && isset($builtQuery))
            {
                $this->benchmark($this->databaseName, $builtQuery);
                $this->logger()->debug($builtQuery, compact('query', 'parameters'));
            }
        }
        catch (\PDOException $exception)
        {
            $this->logger()->error(
                !empty($builtQuery) ? $builtQuery : QueryCompiler::interpolate($query, $parameters),
                compact('query', 'parameters')
            );

            throw QueryException::createFromPDO($exception);
        }

        return $pdoStatement;
    }

    /**
     * Run select type SQL statement with prepare parameters against connected PDO instance.
     * QueryResult will be returned and can be used to walk thought resulted dataset.
     *
     * @param string $query              SQL statement with parameter placeholders.
     * @param array  $parameters         Parameters to be binded into query.
     * @param array  $preparedParameters Processed parameters will be saved into this array.
     * @return QueryResult
     * @throws \PDOException
     */
    public function query($query, array $parameters = [], &$preparedParameters = null)
    {
        return $this->container->get(static::QUERY_RESULT, [
            'statement'  => $this->statement($query, $parameters, $preparedParameters),
            'parameters' => $preparedParameters
        ]);
    }

    /**
     * Driver specific database/table identifier quotation.
     *
     * @param string $identifier
     * @return string
     */
    public function identifier($identifier)
    {
        return $identifier == '*' ? '*' : '"' . str_replace('"', '""', $identifier) . '"';
    }

    /**
     * Driver specific PDOStatement parameters preparation.
     *
     * @param array $parameters
     * @return array
     */
    public function prepareParameters(array $parameters)
    {
        $result = [];
        foreach ($parameters as $parameter)
        {
            if ($parameter instanceof ParameterInterface)
            {
                $parameter = $parameter->getValue();
            }

            if ($parameter instanceof \DateTime)
            {
                //We are going to convert all timestamps to database timezone which is UTC by default
                $parameter = $parameter->setTimezone(
                    new \DateTimeZone(DatabaseManager::DEFAULT_TIMEZONE)
                )->format(static::DATETIME);
            }

            if (is_array($parameter))
            {
                $result = array_merge($result, $this->prepareParameters($parameter));
            }
            else
            {
                $result[] = $parameter;
            }
        }

        return $result;
    }

    /**
     * Get last inserted row id.
     *
     * @param string|null $sequence Name of the sequence object from which the ID should be returned.
     *                              Not required for MySQL database, but should be specified for
     *                              Postgres (Postgres Driver will do it automatically).
     * @return mixed
     */
    public function lastInsertID($sequence = null)
    {
        return $sequence
            ? (int)$this->getPDO()->lastInsertId($sequence)
            : (int)$this->getPDO()->lastInsertId();
    }

    /**
     * Get the number of active transactions (transaction level).
     *
     * @return int
     */
    public function transactionLevel()
    {
        return $this->transactionLevel;
    }

    /**
     * Start SQL transaction with specified isolation level, not all database types support it.
     * Nested transactions will be processed using savepoints.
     *
     * @link   http://en.wikipedia.org/wiki/Database_transaction
     * @link   http://en.wikipedia.org/wiki/Isolation_(database_systems)
     * @param string $isolationLevel No value provided by default.
     * @return bool
     */
    public function beginTransaction($isolationLevel = null)
    {
        $this->transactionLevel++;
        if ($this->transactionLevel == 1)
        {
            if (!empty($isolationLevel))
            {
                $this->isolationLevel($isolationLevel);
            }

            $this->logger()->info('Starting transaction.');

            return $this->getPDO()->beginTransaction();
        }
        else
        {
            $this->savepointCreate($this->transactionLevel);
        }

        return true;
    }

    /**
     * Commit the active database transaction.
     *
     * @return bool
     */
    public function commitTransaction()
    {
        $this->transactionLevel--;
        if ($this->transactionLevel == 0)
        {
            $this->logger()->info('Committing transaction.');

            return $this->getPDO()->commit();
        }
        else
        {
            $this->savepointRelease($this->transactionLevel + 1);
        }

        return true;
    }

    /**
     * Rollback the active database transaction.
     *
     * @return bool
     */
    public function rollbackTransaction()
    {
        $this->transactionLevel--;

        if ($this->transactionLevel == 0)
        {
            $this->logger()->info('Rolling black transaction.');

            return $this->getPDO()->rollBack();
        }
        else
        {
            $this->savepointRollback($this->transactionLevel + 1);
        }

        return true;
    }

    /**
     * Clean (truncate) specified database table. Table should exists at this moment.
     *
     * @param string $table Table name without prefix included.
     */
    public function truncate($table)
    {
        $this->statement("TRUNCATE TABLE {$this->identifier($table)}");
    }

    /**
     * Check if linked database has specified table.
     *
     * @param string $name Fully specified table name, including prefix.
     * @return bool
     */
    abstract public function hasTable($name);

    /**
     * Fetch list of all available table names under linked database, this method is called by Database
     * in getTables() method, same methods will automatically filter tables by their prefix.
     *
     * @return array
     */
    abstract public function tableNames();

    /**
     * Get schema for specified table name, name should be provided without database prefix.
     * TableSchema contains information about all table columns, indexes and foreign keys. Schema can
     * be used to manipulate table structure.
     *
     * @param string $table       Table name without prefix included.
     * @param string $tablePrefix Database specific table prefix, this parameter is not required,
     *                            but if provided all
     *                            foreign keys will be created using it.
     * @return AbstractTable
     */
    public function tableSchema($table, $tablePrefix = '')
    {
        return $this->container->get(static::SCHEMA_TABLE, [
            'driver'      => $this,
            'name'        => $table,
            'tablePrefix' => $tablePrefix
        ]);
    }

    /**
     * Get instance of driver specified ColumnSchema. Every schema object should fully represent one
     * table column, it's type and all possible options.
     *
     * @param AbstractTable $table  Parent TableSchema.
     * @param string        $name   Column name.
     * @param mixed         $schema Driver specific column schema.
     * @return AbstractColumn
     */
    public function columnSchema(AbstractTable $table, $name, $schema = null)
    {
        return $this->container->get(static::SCHEMA_COLUMN, compact('table', 'name', 'schema'));
    }

    /**
     * Get instance of driver specified IndexSchema. Every index schema should represent single table
     * index including name, type and columns.
     *
     * @param AbstractTable $table  Parent TableSchema.
     * @param string        $name   Index name.
     * @param mixed         $schema Driver specific index schema.
     * @return AbstractIndex
     */
    public function indexSchema(AbstractTable $table, $name, $schema = null)
    {
        return $this->container->get(static::SCHEMA_INDEX, compact('table', 'name', 'schema'));
    }

    /**
     * Get instance of driver specified ReferenceSchema (foreign key). Every ReferenceSchema should
     * represent one foreign key with it's referenced table, column and rules.
     *
     * @param AbstractTable $table  Parent TableSchema.
     * @param string        $name   Constraint name.
     * @param mixed         $schema Driver specific foreign key schema.
     * @return AbstractReference
     */
    public function referenceSchema(AbstractTable $table, $name, $schema = null)
    {
        return $this->container->get(static::SCHEMA_REFERENCE, compact('table', 'name', 'schema'));
    }

    /**
     * Current timestamp expression value.
     *
     * @return string
     */
    public function timestampNow()
    {
        return static::TIMESTAMP_NOW;
    }

    /**
     * QueryCompiler is low level SQL compiler which used by different query builders to generate
     * statement based on provided tokens. Every builder will get it's own QueryCompiler at it has
     * some internal isolation features (such as query specific table aliases).
     *
     * @param string $tablePrefix Database specific table prefix, used to correctly quote table names
     *                            and other identifiers.
     * @return QueryCompiler
     */
    public function queryCompiler($tablePrefix = '')
    {
        return $this->container->get(static::QUERY_COMPILER, [
            'driver'      => $this,
            'tablePrefix' => $tablePrefix
        ]);
    }

    /**
     * Get InsertQuery builder with driver specific query compiler.
     *
     * @param Database $database   Database instance builder should be associated to.
     * @param array    $parameters Initial builder parameters.
     * @return InsertQuery
     */
    public function insertBuilder(Database $database, array $parameters = [])
    {
        return $this->container->get(InsertQuery::class, [
                'database' => $database,
                'compiler' => $this->queryCompiler($database->getPrefix())
            ] + $parameters);
    }

    /**
     * Get SelectQuery builder with driver specific query compiler.
     *
     * @param Database $database   Database instance builder should be associated to.
     * @param array    $parameters Initial builder parameters.
     * @return SelectQuery
     */
    public function selectBuilder(Database $database, array $parameters = [])
    {
        return $this->container->get(SelectQuery::class, [
                'database' => $database,
                'compiler' => $this->queryCompiler($database->getPrefix())
            ] + $parameters);
    }

    /**
     * Get DeleteQuery builder with driver specific query compiler.
     *
     * @param Database $database   Database instance builder should be associated to.
     * @param array    $parameters Initial builder parameters.
     * @return DeleteQuery
     */
    public function deleteBuilder(Database $database, array $parameters = [])
    {
        return $this->container->get(DeleteQuery::class, [
                'database' => $database,
                'compiler' => $this->queryCompiler($database->getPrefix())
            ] + $parameters);
    }

    /**
     * Get UpdateQuery builder with driver specific query compiler.
     *
     * @param Database $database   Database instance builder should be associated to.
     * @param array    $parameters Initial builder parameters.
     * @return UpdateQuery
     */
    public function updateBuilder(Database $database, array $parameters = [])
    {
        return $this->container->get(UpdateQuery::class, [
                'database' => $database,
                'compiler' => $this->queryCompiler($database->getPrefix())
            ] + $parameters);
    }

    /**
     * Simplified way to dump information.
     *
     * @return object
     */
    public function __debugInfo()
    {
        return (object)[
            'connection' => $this->config['connection'],
            'connected'  => $this->isConnected(),
            'database'   => $this->getDatabaseName(),
            'options'    => $this->options
        ];
    }

    /**
     * Method used to get PDO instance for current driver, it can be overwritten by custom driver
     * realization to perform DBMS specific operations.
     *
     * @return PDO
     */
    protected function createPDO()
    {
        return new PDO(
            $this->config['connection'],
            $this->config['username'],
            $this->config['password'],
            $this->options
        );
    }

    /**
     * Set transaction isolation level, this feature may not be supported by specific database driver.
     *
     * @param string $level
     */
    protected function isolationLevel($level)
    {
        $this->logger()->info("Setting transaction isolation level to '{$level}'.");
        !empty($level) && $this->statement("SET TRANSACTION ISOLATION LEVEL {$level}");
    }

    /**
     * Create nested transaction save point.
     *
     * @link http://en.wikipedia.org/wiki/Savepoint
     * @param string $name Savepoint name/id, must not contain spaces and be valid database identifier.
     */
    protected function savepointCreate($name)
    {
        $this->logger()->info("Creating savepoint '{$name}'.");
        $this->statement("SAVEPOINT " . $this->identifier("SVP{$name}"));
    }

    /**
     * Commit/release savepoint.
     *
     * @link http://en.wikipedia.org/wiki/Savepoint
     * @param string $name Savepoint name/id, must not contain spaces and be valid database identifier.
     */
    protected function savepointRelease($name)
    {
        $this->logger()->info("Releasing savepoint '{$name}'.");
        $this->statement("RELEASE SAVEPOINT " . $this->identifier("SVP{$name}"));
    }

    /**
     * Rollback savepoint.
     *
     * @link http://en.wikipedia.org/wiki/Savepoint
     * @param string $name Savepoint name/id, must not contain spaces and be valid database identifier.
     */
    protected function savepointRollback($name)
    {
        $this->logger()->info("Rolling back savepoint '{$name}'.");
        $this->statement("ROLLBACK TO SAVEPOINT " . $this->identifier("SVP{$name}"));
    }
}