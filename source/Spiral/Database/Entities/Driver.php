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
use Spiral\Database\DatabaseProvider;
use Spiral\Database\Exceptions\QueryException;
use Spiral\Database\Injections\ParameterInterface;
use Spiral\Database\Query\QueryResult;
use Spiral\Database\Entities\Schemas\AbstractColumn;
use Spiral\Database\Entities\Schemas\AbstractIndex;
use Spiral\Database\Entities\Schemas\AbstractReference;
use Spiral\Database\Entities\Schemas\AbstractTable;
use PDO;
use PDOStatement;
use Spiral\Core\ContainerInterface;
use Spiral\Debug\Traits\BenchmarkTrait;
use Spiral\Debug\Traits\LoggerTrait;

/**
 * Driver abstraction is responsible for DBMS specific set of functions and used by Databases to hide
 * implementation specific functionality.
 */
abstract class Driver extends Component implements LoggerAwareInterface
{
    /**
     * There is few points can raise warning message or take long time to execute, we better profile
     * them.
     */
    use LoggerTrait, BenchmarkTrait;

    /**
     * One of DatabaseInterface types.
     */
    const TYPE = '';

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
     * @var PDO|null
     */
    private $pdo = null;

    /**
     * Transaction level (count of nested transactions). Not all drives can support nested transactions.
     *
     * @var int
     */
    private $transactionLevel = 0;

    /**
     * Database/source name (fetched from connection string).
     *
     * @var string
     */
    protected $source = '';

    /**
     * Connection configuration described in DBAL config file. Any driver can be used as data source
     * for multiple databases as table prefix and quotation defined on Database instance level.
     *
     * @var array
     */
    protected $config = [
        'profiling'  => false,
        'connection' => '',
        'username'   => '',
        'password'   => '',
        'options'    => []
    ];

    /**
     * PDO connection options set.
     *
     * @var array
     */
    protected $options = [
        PDO::ATTR_CASE              => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_STRINGIFY_FETCHES => false
    ];

    /**
     * @var ContainerInterface
     */
    protected $container = null;

    /**
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
            $this->source = $matches[1];
        }
    }

    /**
     * Source name, can include database name or database file.
     *
     * @return string
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * Database type driver linked to.
     *
     * @return string
     */
    public function getType()
    {
        return static::TYPE;
    }

    /**
     * Driver configuration.
     *
     * @return array
     */
    public function config()
    {
        return $this->config;
    }

    /**
     * Enabled profiling will raise set of log messages and benchmarks associated with PDO queries.
     *
     * @param bool $enabled Enable or disable driver profiling.
     * @return $this
     */
    public function setProfiling($enabled = true)
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

        return !empty($this->getPDO());
    }

    /**
     * Disconnect driver.
     *
     * @return $this
     */
    public function disconnect()
    {
        $this->pdo = null;

        return $this;
    }

    /**
     * Check if driver already connected.
     *
     * @return bool
     */
    public function isConnected()
    {
        return (bool)$this->pdo;
    }

    /**
     * Change PDO instance associated with driver.
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
     * Get associated PDO connection. Will automatically connect if such connection does not exists.
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
        $this->pdo = $this->createPDO();
        $this->benchmark('connect', $this->config['connection']);

        return $this->pdo;
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
     * Current timestamp expression value.
     *
     * @return string
     */
    public function timestampNow()
    {
        return static::TIMESTAMP_NOW;
    }

    /**
     * Create instance of PDOStatement using provided SQL query and set of parameters.
     *
     * @param string $query
     * @param array  $parameters         Parameters to be binded into query.
     * @param array  $preparedParameters Prepared list of parameters, reference.
     * @return \PDOStatement
     * @throws QueryException
     */
    public function statement($query, array $parameters = [], &$preparedParameters = null)
    {
        $preparedParameters = $parameters = $this->prepareParameters($parameters);

        try
        {
            if ($this->isProfiling())
            {
                $queryString = QueryCompiler::interpolate($query, $parameters);
                $this->benchmark($this->source, $queryString);
            }

            $pdoStatement = $this->getPDO()->prepare($query);

            //Configuring statement binded parameters
            $pdoStatement->execute($parameters);

            if ($this->isProfiling() && isset($queryString))
            {
                $this->benchmark($this->source, $queryString);
                $this->logger()->debug($queryString, compact('query', 'parameters'));
            }
        }
        catch (\PDOException $exception)
        {
            $this->logger()->error(
                !empty($queryString) ? $queryString : QueryCompiler::interpolate($query, $parameters),
                compact('query', 'parameters')
            );

            throw new QueryException($exception->getMessage(), $exception->getCode(), $exception);
        }

        return $pdoStatement;
    }

    /**
     * Execute sql statement and wrap resulted rows using driver specific or default instance of
     * QueryResult.
     *
     * @param string $query
     * @param array  $parameters         Parameters to be binded into query.
     * @param array  $preparedParameters Prepared list of parameters, reference.
     * @return QueryResult
     * @throws QueryException
     */
    public function query($query, array $parameters = [], &$preparedParameters = null)
    {
        return $this->container->get(static::QUERY_RESULT, [
            'statement'  => $this->statement($query, $parameters, $preparedParameters),
            'parameters' => $preparedParameters
        ]);
    }

    /**
     * Get id of last inserted row, this method must be called after insert query. Attention,
     * such functionality may not work in some DBMS property (Postgres).
     *
     * @param string|null $sequence Name of the sequence object from which the ID should be returned.
     * @return mixed
     */
    public function lastInsertID($sequence = null)
    {
        return $sequence
            ? (int)$this->getPDO()->lastInsertId($sequence)
            : (int)$this->getPDO()->lastInsertId();
    }

    /**
     * Prepare set of query builder/user parameters to be send to PDO. Must convert DateTime instances
     * into valid database timestamps and resolve values of ParameterInterface.
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
                    new \DateTimeZone(DatabaseProvider::DEFAULT_TIMEZONE)
                )->format(static::DATETIME);
            }

            if (is_array($parameter))
            {
                $result = array_merge($result, $this->prepareParameters($parameter));
                continue;
            }

            $result[] = $parameter;
        }

        return $result;
    }

    /**
     * Start SQL transaction with specified isolation level (not all DBMS support it). Nested
     * transactions are processed using savepoints.
     *
     * @link   http://en.wikipedia.org/wiki/Database_transaction
     * @link   http://en.wikipedia.org/wiki/Isolation_(database_systems)
     * @param string $isolationLevel
     * @return bool
     */
    public function beginTransaction($isolationLevel = null)
    {
        $this->transactionLevel++;
        if ($this->transactionLevel == 1)
        {
            if (!empty($isolationLevel))
            {
                $this->setIsolationLevel($isolationLevel);
            }

            $this->logger()->info('Starting transaction.');

            return $this->getPDO()->beginTransaction();
        }

        $this->savepointCreate($this->transactionLevel);

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

        $this->savepointRelease($this->transactionLevel + 1);

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

        $this->savepointRollback($this->transactionLevel + 1);

        return true;
    }

    /**
     * Check if table exists.
     *
     * @param string $name
     * @return bool
     */
    abstract public function hasTable($name);

    /**
     * Clean (truncate) specified driver table.
     *
     * @param string $table Table name with prefix included.
     */
    public function truncate($table)
    {
        $this->statement("TRUNCATE TABLE {$this->identifier($table)}");
    }

    /**
     * Get every available table name as array.
     *
     * @return array
     */
    abstract public function tableNames();

    /**
     * Get Driver specific AbstractTable implementation.
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
     * Get Driver specific AbstractColumn implementation.
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
     * Get Driver specific AbstractIndex implementation.
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
     * Get Driver specific AbstractReference implementation.
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
     * Get instance of Driver specific QueryCompiler.
     *
     * @param string $tablePrefix Database specific table prefix, used to quote table names and build
     *                            aliases.
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
     * @return object
     */
    public function __debugInfo()
    {
        return (object)[
            'connection' => $this->config['connection'],
            'connected'  => $this->isConnected(),
            'database'   => $this->getSource(),
            'options'    => $this->options
        ];
    }

    /**
     * Create instance of configured PDO class.
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
    protected function setIsolationLevel($level)
    {
        $this->logger()->info("Set transaction isolation level to '{$level}'.");
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