<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Entities\Prototypes;

use PDO;
use Psr\Log\LoggerAwareInterface;
use Spiral\Core\Component;
use Spiral\Core\Exceptions\ScopeException;
use Spiral\Database\DatabaseManager;
use Spiral\Database\Entities\Query\PDOResult;
use Spiral\Database\Exceptions\DriverException;
use Spiral\Database\Exceptions\QueryException;
use Spiral\Database\Helpers\QueryInterpolator;
use Spiral\Database\Injections\Parameter;
use Spiral\Database\Injections\ParameterInterface;
use Spiral\Debug\Traits\BenchmarkTrait;
use Spiral\Debug\Traits\LoggerTrait;

/**
 * Basic implementation of DBAL Driver, basically decorates PDO. Extends component to provide access
 *  to functionality like shared loggers and benchmarking.
 */
abstract class PDODriver extends Component implements LoggerAwareInterface
{
    use LoggerTrait, BenchmarkTrait;

    /**
     * One of DatabaseInterface types, must be set on implementation.
     */
    const TYPE = null;

    /**
     * DateTime format to be used to perform automatic conversion of DateTime objects.
     *
     * @var string
     */
    const DATETIME = 'Y-m-d H:i:s';

    /**
     * Driver name.
     *
     * @var string
     */
    private $name = '';

    /**
     * Transaction level (count of nested transactions). Not all drives can support nested
     * transactions.
     *
     * @var int
     */
    private $transactionLevel = 0;

    /**
     * @var PDO|null
     */
    private $pdo = null;

    /**
     * Connection configuration described in DBAL config file. Any driver can be used as data source
     * for multiple databases as table prefix and quotation defined on Database instance level.
     *
     * @var array
     */
    protected $defaultOptions = [
        'profiling'  => false,

        //DSN
        'connection' => '',
        'username'   => '',
        'password'   => '',
        'options'    => [],
    ];

    /**
     * PDO connection options set.
     *
     * @var array
     */
    protected $options = [
        PDO::ATTR_CASE              => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_STRINGIFY_FETCHES => true,
    ];

    /**
     * @param string $name
     * @param array  $options
     *
     * @throws ScopeException
     */
    public function __construct(string $name, array $options)
    {
        $this->name = $name;

        $this->defaultOptions = $options + $this->defaultOptions;

        if (!empty($options['options'])) {
            //PDO connection options has to be stored under key "options" of config
            $this->options = $options['options'] + $this->options;
        }
    }

    /**
     * Source name, can include database name or database file.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get driver source database or file name.
     *
     * @return string
     *
     * @throws DriverException
     */
    public function getSource(): string
    {
        if (preg_match('/(?:dbname|database)=([^;]+)/i', $this->defaultOptions['connection'],
            $matches)) {
            return $matches[1];
        }

        throw new DriverException('Unable to locate source name.');
    }

    /**
     * Database type driver linked to.
     *
     * @return string
     */
    public function getType(): string
    {
        return static::TYPE;
    }

    /**
     * Connection specific timezone, at this moment locked to UTC.
     *
     * @todo Support connection specific timezones.
     *
     * @return \DateTimeZone
     */
    public function getTimezone(): \DateTimeZone
    {
        return new \DateTimeZone(DatabaseManager::DEFAULT_TIMEZONE);
    }

    /**
     * Enabled profiling will raise set of log messages and benchmarks associated with PDO queries.
     *
     * @param bool $enabled Enable or disable driver profiling.
     *
     * @return self
     */
    public function setProfiling(bool $enabled = true): PDODriver
    {
        $this->defaultOptions['profiling'] = $enabled;

        return $this;
    }

    /**
     * Check if profiling mode is enabled.
     *
     * @return bool
     */
    public function isProfiling(): bool
    {
        return $this->defaultOptions['profiling'];
    }

    /**
     * Force driver to connect.
     *
     * @return PDO
     *
     * @throws DriverException
     */
    public function connect(): PDO
    {
        if ($this->isConnected()) {
            return $this->pdo;
        }

        $benchmark = $this->benchmark('connect', $this->defaultOptions['connection']);
        try {
            $this->pdo = $this->createPDO();
        } finally {
            $this->benchmark($benchmark);
        }

        return $this->pdo;
    }

    /**
     * Disconnect driver.
     *
     * @return self
     */
    public function disconnect(): PDODriver
    {
        $this->pdo = null;

        return $this;
    }

    /**
     * Check if driver already connected.
     *
     * @return bool
     */
    public function isConnected(): bool
    {
        return !empty($this->pdo);
    }

    /**
     * Change PDO instance associated with driver.
     *
     * @param PDO $pdo
     *
     * @return self
     */
    public function setPDO(PDO $pdo): PDODriver
    {
        $this->pdo = $pdo;

        return $this;
    }

    /**
     * Get associated PDO connection. Will automatically connect if such connection does not exists.
     *
     * @return PDO
     */
    public function getPDO(): PDO
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        return $this->pdo;
    }

    /**
     * Driver specific database/table identifier quotation.
     *
     * @param string $identifier
     *
     * @return string
     */
    public function identifier(string $identifier): string
    {
        return $identifier == '*' ? '*' : '"' . str_replace('"', '""', $identifier) . '"';
    }

    /**
     * Quote value using PDO.
     *
     * @param mixed $value
     * @param int   $type Parameter type.
     *
     * @return string
     */
    public function quote($value, int $type = PDO::PARAM_STR): string
    {
        if ($value instanceof \DateTimeInterface) {
            $value = $this->resolveDateTime($value);
        }

        return $this->getPDO()->quote($value);
    }

    /**
     * Wraps PDO query method with custom representation class.
     *
     * @param string $statement
     * @param array  $parameters
     *
     * @return PDOResult
     */
    public function query(string $statement, array $parameters = []): PDOResult
    {
        //Forcing specific return class
        $result = $this->statement($statement, $parameters, PDOResult::class);

        /**
         * @var PDOResult $result
         */
        return $result;
    }

    /**
     * Create instance of PDOStatement using provided SQL query and set of parameters and execute
     * it.
     *
     * @param string $query
     * @param array  $parameters Parameters to be binded into query.
     * @param string $class      Class to be used to represent results.
     * @param array  $args       Class construction arguments (by default filtered parameters)
     *
     * @return \PDOStatement
     *
     * @throws QueryException
     */
    public function statement(
        string $query,
        array $parameters = [],
        $class = PDOResult::class,
        array $args = []
    ): \PDOStatement {
        try {
            //Filtered and normalized parameters
            $parameters = $this->flattenParameters($parameters);

            if ($this->isProfiling()) {
                $queryString = QueryInterpolator::interpolate($query, $parameters);
                $benchmark = $this->benchmark($this->name, $queryString);
            }

            //PDOStatement instance (prepared)
            $pdoStatement = $this->prepare($query, $class, !empty($args) ? $args : [$parameters]);

            //Mounting all input parameters
            $pdoStatement = $this->bindParameters($pdoStatement, $parameters);

            try {
                $pdoStatement->execute();
            } finally {
                if (!empty($benchmark)) {
                    $this->benchmark($benchmark);
                }
            }

            //Only exists if profiling on
            if (!empty($queryString)) {
                $this->logger()->info($queryString, compact('query', 'parameters'));
            }

        } catch (\PDOException $e) {
            if (empty($queryString)) {
                $queryString = QueryInterpolator::interpolate($query, $parameters);
            }

            //Logging error even when no profiling is enabled
            $this->logger()->error($queryString, compact('query', 'parameters'));

            //Converting exception into query or integrity exception
            throw $this->clarifyException($e);
        }

        return $pdoStatement;
    }

    /**
     * Get prepared PDO statement.
     *
     * @param string $statement Query statement.
     * @param string $class     Class to represent PDO statement.
     * @param array  $args      Class construction arguments (by default paramaters)
     *
     * @return \PDOStatement
     */
    public function prepare(
        string $statement,
        $class = \PDOStatement::class,
        array $args = []
    ): \PDOStatement {
        $pdo = $this->getPDO();

        $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [$class, $args]);

        return $pdo->prepare($statement);
    }

    /**
     * Get id of last inserted row, this method must be called after insert query. Attention,
     * such functionality may not work in some DBMS property (Postgres).
     *
     * @param string|null $sequence Name of the sequence object from which the ID should be
     *                              returned.
     *
     * @return mixed
     */
    public function lastInsertID(string $sequence = null)
    {
        $pdo = $this->getPDO();

        return $sequence ? (int)$pdo->lastInsertId($sequence) : (int)$pdo->lastInsertId();
    }

    /**
     * Prepare set of query builder/user parameters to be send to PDO. Must convert DateTime
     * instances into valid database timestamps and resolve values of ParameterInterface.
     *
     * Every value has to wrapped with parameter interface.
     *
     * @param array $parameters
     *
     * @return ParameterInterface[]
     *
     * @throws DriverException
     */
    public function flattenParameters(array $parameters): array
    {
        $flatten = [];
        foreach ($parameters as $key => $parameter) {
            if (!$parameter instanceof ParameterInterface) {
                //Let's wrap value
                $parameter = new Parameter($parameter, Parameter::DETECT_TYPE);
            }

            if ($parameter->isArray()) {
                if (!is_numeric($key)) {
                    throw new DriverException("Array parameters can not be named");
                }

                //Flattening arrays
                $nestedParameters = $parameter->flatten();

                /**
                 * @var ParameterInterface $parameter []
                 */
                foreach ($nestedParameters as &$nestedParameter) {
                    if ($nestedParameter->getValue() instanceof \DateTime) {

                        //Original parameter must not be altered
                        $nestedParameter = $nestedParameter->withValue(
                            $this->resolveDateTime($nestedParameter->getValue())
                        );
                    }

                    unset($nestedParameter);
                }

                //Quick and dirty
                $flatten = array_merge($flatten, $nestedParameters);

            } else {
                if ($parameter->getValue() instanceof \DateTime) {
                    //Original parameter must not be altered
                    $parameter = $parameter->withValue(
                        $this->resolveDateTime($parameter->getValue())
                    );
                }

                if (is_numeric($key)) {
                    //Numeric keys can be shifted
                    $flatten[] = $parameter;
                } else {
                    $flatten[$key] = $parameter;
                }
            }
        }

        return $flatten;
    }

    /**
     * Start SQL transaction with specified isolation level (not all DBMS support it). Nested
     * transactions are processed using savepoints.
     *
     * @link   http://en.wikipedia.org/wiki/Database_transaction
     * @link   http://en.wikipedia.org/wiki/Isolation_(database_systems)
     *
     * @param string $isolationLevel
     *
     * @return bool
     */
    public function beginTransaction(string $isolationLevel = null): bool
    {
        ++$this->transactionLevel;

        if ($this->transactionLevel == 1) {
            if (!empty($isolationLevel)) {
                $this->isolationLevel($isolationLevel);
            }

            if ($this->isProfiling()) {
                $this->logger()->info('Begin transaction');
            }

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
    public function commitTransaction(): bool
    {
        --$this->transactionLevel;

        if ($this->transactionLevel == 0) {
            if ($this->isProfiling()) {
                $this->logger()->info('Commit transaction');
            }

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
    public function rollbackTransaction(): bool
    {
        --$this->transactionLevel;

        if ($this->transactionLevel == 0) {
            if ($this->isProfiling()) {
                $this->logger()->info('Rollback transaction');
            }

            return $this->getPDO()->rollBack();
        }

        $this->savepointRollback($this->transactionLevel + 1);

        return true;
    }

    /**
     * @return array
     */
    public function __debugInfo()
    {
        return [
            'connection' => $this->defaultOptions['connection'],
            'connected'  => $this->isConnected(),
            'profiling'  => $this->isProfiling(),
            'source'     => $this->getSource(),
            'options'    => $this->options,
        ];
    }

    /**
     * Create instance of configured PDO class.
     *
     * @return PDO
     */
    protected function createPDO(): PDO
    {
        return new PDO(
            $this->defaultOptions['connection'],
            $this->defaultOptions['username'],
            $this->defaultOptions['password'],
            $this->options
        );
    }

    /**
     * Convert PDO exception into query or integrity exception.
     *
     * @param \PDOException $exception
     *
     * @return QueryException
     */
    protected function clarifyException(\PDOException $exception): QueryException
    {
        //@todo more exceptions to be thrown
        return new QueryException($exception);
    }

    /**
     * Set transaction isolation level, this feature may not be supported by specific database
     * driver.
     *
     * @param string $level
     */
    protected function isolationLevel(string $level)
    {
        if ($this->isProfiling()) {
            $this->logger()->info("Set transaction isolation level to '{$level}'");
        }

        if (!empty($level)) {
            $this->statement("SET TRANSACTION ISOLATION LEVEL {$level}");
        }
    }

    /**
     * Create nested transaction save point.
     *
     * @link http://en.wikipedia.org/wiki/Savepoint
     *
     * @param string $name Savepoint name/id, must not contain spaces and be valid database
     *                     identifier.
     */
    protected function savepointCreate(string $name)
    {
        if ($this->isProfiling()) {
            $this->logger()->info("Creating savepoint '{$name}'");
        }

        $this->statement('SAVEPOINT ' . $this->identifier("SVP{$name}"));
    }

    /**
     * Commit/release savepoint.
     *
     * @link http://en.wikipedia.org/wiki/Savepoint
     *
     * @param string $name Savepoint name/id, must not contain spaces and be valid database
     *                     identifier.
     */
    protected function savepointRelease(string $name)
    {
        if ($this->isProfiling()) {
            $this->logger()->info("Releasing savepoint '{$name}'");
        }

        $this->statement('RELEASE SAVEPOINT ' . $this->identifier("SVP{$name}"));
    }

    /**
     * Rollback savepoint.
     *
     * @link http://en.wikipedia.org/wiki/Savepoint
     *
     * @param string $name Savepoint name/id, must not contain spaces and be valid database
     *                     identifier.
     */
    protected function savepointRollback(string $name)
    {
        if ($this->isProfiling()) {
            $this->logger()->info("Rolling back savepoint '{$name}'");
        }
        $this->statement('ROLLBACK TO SAVEPOINT ' . $this->identifier("SVP{$name}"));
    }

    /**
     * Convert DateTime object into local database representation. Driver will automatically force
     * needed timezone.
     *
     * @param \DateTimeInterface $value
     *
     * @return string
     */
    protected function resolveDateTime(\DateTimeInterface $value): string
    {
        $datetime = new \DateTime();
        $datetime->setTimestamp($value->getTimestamp());
        $datetime->setTimezone($this->getTimezone());

        return $datetime->format(static::DATETIME);
    }

    /**
     * Bind parameters into statement.
     *
     * @param \PDOStatement        $statement
     * @param ParameterInterface[] $parameters Named hash of ParameterInterface.
     *
     * @return \PDOStatement
     */
    private function bindParameters(\PDOStatement $statement, array $parameters): \PDOStatement
    {
        foreach ($parameters as $index => $parameter) {
            if (is_numeric($index)) {
                //Numeric, @see http://php.net/manual/en/pdostatement.bindparam.php
                $statement->bindValue($index + 1, $parameter->getValue(), $parameter->getType());
            } else {
                //Named
                $statement->bindValue($index, $parameter->getValue(), $parameter->getType());
            }
        }

        return $statement;
    }
}
