<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Entities;

use Psr\Log\LoggerInterface;
use Spiral\Cache\StoreInterface;
use Spiral\Core\FactoryInterface;
use Spiral\Database\Builders\DeleteQuery;
use Spiral\Database\Builders\InsertQuery;
use Spiral\Database\Builders\SelectQuery;
use Spiral\Database\Builders\UpdateQuery;
use Spiral\Database\Entities\Prototypes\PDODriver;
use Spiral\Database\Schemas\Prototypes\AbstractTable;

/**
 * Driver abstraction is responsible for DBMS specific set of functions and used by Databases to
 * hide implementation specific functionality. Extends PDODriver and adds ability to create driver
 * specific query builders and schemas (basically operates like a factory).
 */
abstract class Driver extends PDODriver
{
    /**
     * Schema table class.
     */
    const TABLE_SCHEMA_CLASS = '';

    /**
     * Commander used to execute commands. :).
     */
    const COMMANDER = '';

    /**
     * Query compiler class.
     */
    const QUERY_COMPILER = '';

    /**
     * Transaction level (count of nested transactions). Not all drives can support nested
     * transactions.
     *
     * @var int
     */
    private $transactionLevel = 0;


    /**
     * @var FactoryInterface
     */
    protected $factory = null;

    /**
     * @param string           $name
     * @param array            $options
     * @param FactoryInterface $factory Required to build instances of query builders and compilers.
     */
    public function __construct(string $name, array $options, FactoryInterface $factory)
    {
        parent::__construct($name, $options);

        $this->factory = $factory;
    }

    /**
     * Set cache store to be used by driver.
     *
     * @param StoreInterface $store
     *
     * @return self|$this
     */
    public function setStore(StoreInterface $store): Driver
    {
        $this->cacheStore = $store;

        return $this;
    }

    /**
     * Check if table exists.
     *
     * @param string $name
     *
     * @return bool
     */
    abstract public function hasTable(string $name): bool;

    /**
     * Clean (truncate) specified driver table.
     *
     * @param string $table Table name with prefix included.
     */
    abstract public function truncateData(string $table);

    /**
     * Get every available table name as array.
     *
     * @return array
     */
    abstract public function tableNames(): array;

    /**
     * Get Driver specific AbstractTable implementation.
     *
     * @param string $table  Table name without prefix included.
     * @param string $prefix Database specific table prefix, this parameter is not required,
     *                       but if provided all
     *                       foreign keys will be created using it.
     *
     * @return AbstractTable
     */
    public function tableSchema(string $table, string $prefix = ''): AbstractTable
    {
        return $this->factory->make(
            static::TABLE_SCHEMA_CLASS,
            ['driver' => $this, 'name' => $table, 'prefix' => $prefix]
        );
    }

    /**
     * Get instance of Driver specific QueryCompiler.
     *
     * @param string $prefix Database specific table prefix, used to quote table names and build
     *                       aliases.
     *
     * @return QueryCompiler
     */
    public function queryCompiler(string $prefix = ''): QueryCompiler
    {
        return $this->factory->make(
            static::QUERY_COMPILER,
            ['driver' => $this, 'quoter' => new Quoter($this, $prefix)]
        );
    }

    /**
     * Get InsertQuery builder with driver specific query compiler.
     *
     * @param string $prefix     Database specific table prefix, used to quote table names and build
     *                           aliases.
     * @param array  $parameters Initial builder parameters.
     *
     * @return InsertQuery
     */
    public function insertBuilder(string $prefix, array $parameters = []): InsertQuery
    {
        return $this->factory->make(
            InsertQuery::class,
            ['driver' => $this, 'compiler' => $this->queryCompiler($prefix)] + $parameters
        );
    }

    /**
     * Get SelectQuery builder with driver specific query compiler.
     *
     * @param string $prefix     Database specific table prefix, used to quote table names and build
     *                           aliases.
     * @param array  $parameters Initial builder parameters.
     *
     * @return SelectQuery
     */
    public function selectBuilder(string $prefix, array $parameters = []): SelectQuery
    {
        return $this->factory->make(
            SelectQuery::class,
            ['driver' => $this, 'compiler' => $this->queryCompiler($prefix)] + $parameters
        );
    }

    /**
     * Get DeleteQuery builder with driver specific query compiler.
     *
     * @param string $prefix     Database specific table prefix, used to quote table names and build
     *                           aliases.
     * @param array  $parameters Initial builder parameters.
     *
     * @return DeleteQuery
     */
    public function deleteBuilder(string $prefix, array $parameters = []): DeleteQuery
    {
        return $this->factory->make(
            DeleteQuery::class,
            ['driver' => $this, 'compiler' => $this->queryCompiler($prefix)] + $parameters
        );
    }

    /**
     * Get UpdateQuery builder with driver specific query compiler.
     *
     * @param string $prefix     Database specific table prefix, used to quote table names and build
     *                           aliases.
     * @param array  $parameters Initial builder parameters.
     *
     * @return UpdateQuery
     */
    public function updateBuilder(string $prefix, array $parameters = []): UpdateQuery
    {
        return $this->factory->make(
            UpdateQuery::class,
            ['driver' => $this, 'compiler' => $this->queryCompiler($prefix)] + $parameters
        );
    }

    /**
     * Handler responsible for schema related operations. Handlers responsible for sync flow of
     * tables and columns, provide logger to aggregate all logger operations.
     *
     * @param LoggerInterface $logger
     *
     * @return AbstractHandler
     */
    abstract public function getHandler(LoggerInterface $logger = null): AbstractHandler;

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
}
