<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Entities;

use Spiral\Cache\StoreInterface;
use Spiral\Core\FactoryInterface;
use Spiral\Database\Builders\DeleteQuery;
use Spiral\Database\Builders\InsertQuery;
use Spiral\Database\Builders\SelectQuery;
use Spiral\Database\Builders\UpdateQuery;
use Spiral\Database\Entities\Prototypes\PDODriver;
use Spiral\Database\Entities\Query\CachedResult;
use Spiral\Database\Exceptions\DriverException;
use Spiral\Database\Exceptions\QueryException;
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
     * Default datetime value.
     */
    const DEFAULT_DATETIME = '1970-01-01 00:00:00';

    /**
     * Default timestamp expression.
     */
    const TIMESTAMP_NOW = 'DRIVER_SPECIFIC_NOW_EXPRESSION';

    /**
     * Associated cache store, if any.
     *
     * @var StoreInterface
     */
    protected $cacheStore = null;

    /**
     * @var FactoryInterface
     */
    protected $factory = null;

    /**
     * @param string           $name
     * @param array            $options
     * @param FactoryInterface $factory Required to build instances of query builders and compilers.
     * @param StoreInterface   $store   Cache store associated with driver (optional).
     */
    public function __construct(
        string $name,
        array $options,
        FactoryInterface $factory,
        StoreInterface $store = null
    ) {
        parent::__construct($name, $options);

        $this->factory = $factory;
        $this->cacheStore = $store;
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
     * Execute statement or fetch result from cache and return cached query iterator.
     *
     * @param string         $query
     * @param array          $parameters Parameters to be binded into query.
     * @param int            $lifetime   Cache lifetime in seconds.
     * @param string         $key        Cache key to be used to store query result.
     * @param StoreInterface $store      Cache store to store result in, if null default store will
     *                                   be used.
     *
     * @return CachedResult
     *
     * @throws DriverException
     * @throws QueryException
     */
    public function cachedQuery(
        string $query,
        array $parameters = [],
        int $lifetime,
        string $key = '',
        StoreInterface $store = null
    ) {
        if (empty($store)) {
            if (empty($this->cacheStore)) {
                throw new DriverException("StoreInterface is missing");
            }

            $store = $this->cacheStore;
        }

        if (empty($key)) {
            //Trying to build unique query id based on provided options and environment.
            $key = md5(serialize([$query, $parameters, $this->getName()]));
        }

        $data = $store->remember($key, $lifetime, function () use ($query, $parameters) {
            return $this->query($query, $parameters)->fetchAll();
        });

        return new CachedResult($data, $parameters, $query, $key, $store);
    }

    /**
     * Current timestamp expression value.
     *
     * @return string
     */
    public function nowExpression(): string
    {
        return static::TIMESTAMP_NOW;
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
//        return $this->factory->make(
//            static::SCHEMA_TABLE,
//            [
//                'driver'    => $this,
//                'name'      => $table,
//                'prefix'    => $prefix,
//                'commander' => $this->factory->make(static::COMMANDER, ['driver' => $this]),
//            ]
//        );
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
}
