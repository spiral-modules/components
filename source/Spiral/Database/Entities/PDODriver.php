<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Database\Entities;

use PDO;
use Psr\Log\LoggerAwareInterface;
use Spiral\Core\Component;
use Spiral\Core\ContainerInterface;
use Spiral\Core\Exceptions\SugarException;
use Spiral\Core\Traits\SaturateTrait;
use Spiral\Database\DatabaseManager;
use Spiral\Database\Exceptions\DriverException;
use Spiral\Database\Exceptions\QueryException;
use Spiral\Database\Injections\Parameter;
use Spiral\Database\Injections\ParameterInterface;
use Spiral\Database\Query\QueryResult;
use Spiral\Debug\Traits\BenchmarkTrait;
use Spiral\Debug\Traits\LoggerTrait;

/**
 * Basic implementation of DBAL Driver, can talk to PDO, send queries and etc.
 */
abstract class PDODriver extends Component implements LoggerAwareInterface
{
    /**
     * There is few points can raise warning message or take long time to execute, we better profile
     * them.
     */
    use LoggerTrait, BenchmarkTrait, SaturateTrait;

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
     * @var PDO|null
     */
    private $pdo = null;

    /**
     * Transaction level (count of nested transactions). Not all drives can support nested
     * transactions.
     *
     * @var int
     */
    private $transactionLevel = 0;

    /**
     * Driver name.
     *
     * @var string
     */
    private $name = '';

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
     * Container is needed to construct instances of QueryCompiler.
     *
     * @invisible
     * @var ContainerInterface
     */
    protected $container = null;

    /**
     * @param string             $name
     * @param array              $config
     * @param ContainerInterface $container Container is needed to construct instances of
     *                                      QueryCompiler.
     * @throws SugarException
     */
    public function __construct($name, array $config, ContainerInterface $container = null)
    {
        $this->name = $name;

        $this->config = $config + $this->config;

        //PDO connection options has to be stored under key "options" of config
        $this->options = $config['options'] + $this->options;

        $this->container = $this->saturate($container, ContainerInterface::class);
    }

    /**
     * Source name, can include database name or database file.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get driver source database or file name.
     *
     * @return string
     * @throws DriverException
     */
    public function getSource()
    {
        if (preg_match('/(?:dbname|database)=([^;]+)/i', $this->config['connection'], $matches)) {
            return $matches[1];
        }

        throw new DriverException("Unable to locate source name.");
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
        if (!empty($this->pdo)) {
            return $this->pdo;
        }

        $benchmark = $this->benchmark('connect', $this->config['connection']);
        try {
            $this->pdo = $this->createPDO();
        } finally {
            $this->benchmark($benchmark);
        }

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
     * Create instance of PDOStatement using provided SQL query and set of parameters.
     *
     * @param string $query
     * @param array  $parameters Parameters to be binded into query.
     * @return \PDOStatement
     * @throws QueryException
     */
    public function statement($query, array $parameters = [])
    {
        try {
            if ($this->isProfiling()) {
                $queryString = QueryInterpolator::interpolate($query, $parameters);
                $benchmark = $this->benchmark($this->name, $queryString);
            }

            //Prepared statement
            $pdoStatement = $this->getPDO()->prepare($query);

            foreach ($this->flattenParameters($parameters) as $index => $parameter) {
                //Let's mount statement parameters
                $pdoStatement->bindValue($index + 1, $parameter->getValue(), $parameter->getType());
            }

            try {
                $pdoStatement->execute();
            } finally {
                if (!empty($benchmark)) {
                    $this->benchmark($benchmark);
                }
            }

            if (!empty($queryString)) {
                $this->logger()->info($queryString, compact('query', 'parameters'));
            }
        } catch (\PDOException $exception) {
            if (empty($queryString)) {
                $queryString = QueryInterpolator::interpolate($query, $parameters);
            }

            $this->logger()->error($queryString, compact('query', 'parameters'));

            //Consistency
            throw new QueryException($exception);
        }

        return $pdoStatement;
    }

    /**
     * Execute sql statement and wrap resulted rows using driver specific or default instance of
     * QueryResult.
     *
     * @param string $query
     * @param array  $parameters Parameters to be binded into query.
     * @return QueryResult
     * @throws QueryException
     */
    public function query($query, array $parameters = [])
    {
        return $this->container->construct(static::QUERY_RESULT, [
            'statement'  => $this->statement($query, $parameters),
            'parameters' => $parameters
        ]);
    }

    /**
     * Get id of last inserted row, this method must be called after insert query. Attention,
     * such functionality may not work in some DBMS property (Postgres).
     *
     * @param string|null $sequence Name of the sequence object from which the ID should be
     *                              returned.
     * @return mixed
     */
    public function lastInsertID($sequence = null)
    {
        return $sequence
            ? (int)$this->getPDO()->lastInsertId($sequence)
            : (int)$this->getPDO()->lastInsertId();
    }

    /**
     * Prepare set of query builder/user parameters to be send to PDO. Must convert DateTime
     * instances into valid database timestamps and resolve values of ParameterInterface.
     *
     * Every value has to wrapped with parameter interface.
     *
     * @param array $parameters
     * @return ParameterInterface[]
     */
    public function flattenParameters(array $parameters)
    {
        $flatten = [];
        foreach ($parameters as $parameter) {
            if (!$parameter instanceof ParameterInterface) {
                //Let's wrap value
                $parameter = new Parameter($parameter, Parameter::DETECT_TYPE);
            }

            //Converting into flat array
            $flattenParameter = $parameter->flatten();

            /**
             * @var ParameterInterface $value
             */
            foreach ($flattenParameter as $value) {
                if ($value->getValue() instanceof \DateTime) {
                    //Converting datetime object into string representation
                    $value->setValue($this->prepareDateTime($value->getValue()));
                }
            }

            //We have to flatten all parameters as some of them can be provided in array form
            $flatten = array_merge($flatten, $flattenParameter);
        }

        return $flatten;
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
        if ($this->transactionLevel == 1) {
            if (!empty($isolationLevel)) {
                $this->isolationLevel($isolationLevel);
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
        if ($this->transactionLevel == 0) {
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

        if ($this->transactionLevel == 0) {
            $this->logger()->info('Rolling black transaction.');

            return $this->getPDO()->rollBack();
        }

        $this->savepointRollback($this->transactionLevel + 1);

        return true;
    }

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
     * Get instance of Driver specific QueryCompiler.
     *
     * @param string $prefix Database specific table prefix, used to quote table names and build
     *                       aliases.
     * @return QueryCompiler
     */
    public function queryCompiler($prefix = '')
    {
        return $this->container->construct(static::QUERY_COMPILER, [
            'driver' => $this,
            'quoter' => new Quoter($this, $prefix)
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
     * Set transaction isolation level, this feature may not be supported by specific database
     * driver.
     *
     * @param string $level
     */
    protected function isolationLevel($level)
    {
        $this->logger()->info("Set transaction isolation level to '{$level}'.");

        if (!empty($level)) {
            $this->statement("SET TRANSACTION ISOLATION LEVEL {$level}");
        }
    }

    /**
     * Create nested transaction save point.
     *
     * @link http://en.wikipedia.org/wiki/Savepoint
     * @param string $name Savepoint name/id, must not contain spaces and be valid database
     *                     identifier.
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
     * @param string $name Savepoint name/id, must not contain spaces and be valid database
     *                     identifier.
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
     * @param string $name Savepoint name/id, must not contain spaces and be valid database
     *                     identifier.
     */
    protected function savepointRollback($name)
    {
        $this->logger()->info("Rolling back savepoint '{$name}'.");
        $this->statement("ROLLBACK TO SAVEPOINT " . $this->identifier("SVP{$name}"));
    }

    /**
     * Convert DateTime object into local database representation. Driver will automatically force
     * needed timezone.
     *
     * @param \DateTime $dateTime
     * @return string
     */
    protected function prepareDateTime(\DateTime $dateTime)
    {
        return $dateTime->setTimezone(new \DateTimeZone(DatabaseManager::DEFAULT_TIMEZONE))->format(
            static::DATETIME
        );
    }
}