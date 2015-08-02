<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Entities;

use Spiral\Cache\StoreInterface;
use Spiral\Core\Component;
use Spiral\Core\ContainerInterface;
use Spiral\Database\Builders\DeleteQuery;
use Spiral\Database\Builders\InsertQuery;
use Spiral\Database\Builders\SelectQuery;
use Spiral\Database\Builders\UpdateQuery;
use Spiral\Database\DatabaseManager;
use Spiral\Database\Exceptions\DriverException;
use Spiral\Database\Exceptions\QueryException;
use Spiral\Database\Query\CachedResult;
use Spiral\Database\Query\QueryResult;
use Spiral\Events\Traits\EventsTrait;

/**
 * Database class is high level abstraction at top of Driver. Multiple databases can use same driver
 * and use different by table prefix. Databases usually linked to real database or logical portion
 * of database (filtered by prefix).
 */
class Database extends Component
{
    /**
     * Query and statement events.
     */
    use EventsTrait;

    /**
     * This is magick constant used by Spiral Container, it helps system to resolve controllable
     * injections.
     */
    const INJECTOR = DatabaseManager::class;

    /**
     * Transaction isolation level 'SERIALIZABLE'.
     *
     * This is the highest isolation level. With a lock-based concurrency control DBMS implementation,
     * serializability requires read and write locks (acquired on selected data) to be released at
     * the end of the transaction. Also range-locks must be acquired when a SELECT query uses a ranged
     * WHERE clause, especially to avoid the phantom reads phenomenon (see below).
     *
     * When using non-lock based concurrency control, no locks are acquired; however, if the system
     * detects a write collision among several concurrent transactions, only one of them is allowed
     * to commit. See snapshot isolation for more details on this topic.
     *
     * @link http://en.wikipedia.org/wiki/Isolation_(database_systems)
     */
    const ISOLATION_SERIALIZABLE = 'SERIALIZABLE';

    /**
     * Transaction isolation level 'REPEATABLE READ'.
     *
     * In this isolation level, a lock-based concurrency control DBMS implementation keeps read and
     * write locks (acquired on selected data) until the end of the transaction. However, range-locks
     * are not managed, so phantom reads can occur.
     *
     * @link http://en.wikipedia.org/wiki/Isolation_(database_systems)
     */
    const ISOLATION_REPEATABLE_READ = 'REPEATABLE READ';

    /**
     * Transaction isolation level 'READ COMMITTED'.
     *
     * In this isolation level, a lock-based concurrency control DBMS implementation keeps write locks
     * (acquired on selected data) until the end of the transaction, but read locks are released as
     * soon as the SELECT operation is performed (so the non-repeatable reads phenomenon can occur in
     * this isolation level, as discussed below). As in the previous level, range-locks are not managed.
     *
     * Putting it in simpler words, read committed is an isolation level that guarantees that any data
     * read is committed at the moment it is read. It simply restricts the reader from seeing any
     * intermediate, uncommitted, 'dirty' read. It makes no promise whatsoever that if the transaction
     * re-issues the read, it will find the same data; data is free to change after it is read.
     *
     * @link http://en.wikipedia.org/wiki/Isolation_(database_systems)
     */
    const ISOLATION_READ_COMMITTED = 'READ COMMITTED';

    /**
     * Transaction isolation level 'READ UNCOMMITTED'.
     *
     * This is the lowest isolation level. In this level, dirty reads are allowed, so one transaction
     * may see not-yet-committed changes made by other transactions.
     *
     * Since each isolation level is stronger than those below, in that no higher isolation level
     * allows an action forbidden by a lower one, the standard permits a DBMS to run a transaction
     * at an isolation level stronger than that requested (e.g., a "Read committed" transaction may
     * actually be performed at a "Repeatable read" isolation level).
     *
     * @link http://en.wikipedia.org/wiki/Isolation_(database_systems)
     */
    const ISOLATION_READ_UNCOMMITTED = 'READ UNCOMMITTED';

    /**
     * Default timestamp expression (must be handler by driver as native expressions).
     */
    const TIMESTAMP_NOW = 'DRIVER_SPECIFIC_NOW_EXPRESSION';

    /**
     * @var Driver
     */
    private $driver = null;

    /**
     * @var string
     */
    private $name = '';

    /**
     * @var string
     */
    private $tablePrefix = '';

    /**
     * @invisible
     * @var ContainerInterface
     */
    protected $container = null;

    /**
     * @param ContainerInterface $container
     * @param Driver             $driver      Driver instance responsible for database connection.
     * @param string             $name        Internal database name/id.
     * @param string             $tablePrefix Default database table prefix, will be used for all
     *                                        table identifiers.
     */
    public function __construct(ContainerInterface $container, Driver $driver, $name, $tablePrefix = '')
    {
        $this->driver = $driver;
        $this->name = $name;
        $this->setPrefix($tablePrefix);

        $this->container = $container;
    }

    /**
     * @return Driver
     */
    public function getDriver()
    {
        return $this->driver;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $tablePrefix
     * @return $this
     */
    public function setPrefix($tablePrefix)
    {
        $this->tablePrefix = $tablePrefix;

        return $this;
    }

    /**
     * @return string
     */
    public function getPrefix()
    {
        return $this->tablePrefix;
    }

    /**
     * Get instance of PDOStatement from Driver.
     *
     * @param string $query
     * @param array  $parameters Parameters to be binded into query.
     * @return \PDOStatement
     * @throws DriverException
     * @throws QueryException
     * @event statement($statement, $query, $parameters, $database): statement
     */
    public function statement($query, array $parameters = [])
    {
        return $this->fire('statement', [
            'statement'  => $this->driver->statement($query, $parameters),
            'query'      => $query,
            'parameters' => $parameters,
            'database'   => $this
        ])['statement'];
    }

    /**
     * Execute statement and return number of affected rows.
     *
     * @param string $query
     * @param array  $parameters Parameters to be binded into query.
     * @return int
     * @throws DriverException
     * @throws QueryException
     */
    public function execute($query, array $parameters = [])
    {
        return $this->statement($query, $parameters)->rowCount();
    }

    /**
     * Execute statement and return query iterator.
     *
     * @param string $query
     * @param array  $parameters Parameters to be binded into query.
     * @return QueryResult
     * @throws DriverException
     * @throws QueryException
     * @event statement($statement, $query, $parameters, $database): statement
     */
    public function query($query, array $parameters = [])
    {
        return $this->fire('query', [
            'statement'  => $this->driver->query($query, $parameters),
            'query'      => $query,
            'parameters' => $parameters,
            'database'   => $this
        ])['statement'];
    }

    /**
     * Execute statement or fetch result from cache and return cached query iterator.
     *
     * @param int            $lifetime   Cache lifetime in seconds.
     * @param string         $query      SQL statement with parameter placeholders.
     * @param array          $parameters Parameters to be binded into query.
     * @param string         $key        Cache key to be used to store query result.
     * @param StoreInterface $store      Cache store to store result in, if null default store will
     *                                   be used.
     * @return CachedResult
     * @throws DriverException
     * @throws QueryException
     */
    public function cached($lifetime, $query, array $parameters = [], $key = '', StoreInterface $store = null)
    {
        $store = !empty($store) ? $store : $this->container->get(StoreInterface::class);

        if (empty($key))
        {
            //Trying to build unique query id based on provided options and environment.
            $key = md5(serialize([$query, $parameters, $this->name, $this->tablePrefix]));
        }

        $data = $store->remember($key, $lifetime, function () use ($query, $parameters)
        {
            return $this->query($query, $parameters)->fetchAll();
        });

        return new CachedResult($store, $key, $query, $parameters, $data);
    }

    /**
     * Get instance of InsertBuilder associated with current Database.
     *
     * @param string $table Table where values should be inserted to.
     * @return InsertQuery
     */
    public function insert($table = '')
    {
        return $this->driver->insertBuilder($this, compact('table'));
    }

    /**
     * Get instance of UpdateBuilder associated with current Database.
     *
     * @param string $table  Table where rows should be updated in.
     * @param array  $values Initial set of columns to update associated with their values.
     * @param array  $where  Initial set of where rules specified as array.
     * @return UpdateQuery
     */
    public function update($table = '', array $values = [], array $where = [])
    {
        return $this->driver->updateBuilder($this, compact('table', 'values', 'where'));
    }

    /**
     * Get instance of DeleteBuilder associated with current Database.
     *
     * @param string $table Table where rows should be deleted from.
     * @param array  $where Initial set of where rules specified as array.
     * @return DeleteQuery
     */
    public function delete($table = '', array $where = [])
    {
        return $this->driver->deleteBuilder($this, compact('table', 'where'));
    }

    /**
     * Get instance of SelectBuilder associated with current Database.
     *
     * @param array|string $columns Columns to select.
     * @return SelectQuery
     */
    public function select($columns = '*')
    {
        $columns = func_get_args();
        if (is_array($columns) && isset($columns[0]) && is_array($columns[0]))
        {
            //Can be required in some cases while collecting data from Table->select(), stupid bug.
            $columns = $columns[0];
        }

        return $this->driver->selectBuilder($this, ['columns' => $columns]);
    }

    /**
     * Execute multiple commands defined by Closure function inside one transaction. Closure or
     * function will receive only one argument - Database instance.
     *
     * @param callable $callback
     * @param string   $isolationLevel
     * @return mixed
     * @throws \Exception
     */
    public function transaction(callable $callback, $isolationLevel = null)
    {
        $this->begin($isolationLevel);

        try
        {
            $result = call_user_func($callback, $this);
            $this->commit();

            return $result;
        }
        catch (\Exception $exception)
        {
            $this->rollBack();
            throw $exception;
        }
    }

    /**
     * Start SQL transaction with specified isolation level, not all database types support it.
     *
     * @link http://en.wikipedia.org/wiki/Database_transaction
     * @link http://en.wikipedia.org/wiki/Isolation_(database_systems)
     * @param string $isolationLevel
     * @return bool
     */
    public function begin($isolationLevel = null)
    {
        return $this->driver->beginTransaction($isolationLevel);
    }

    /**
     * Commit the active database transaction.
     *
     * @return bool
     */
    public function commit()
    {
        return $this->driver->commitTransaction();
    }

    /**
     * Rollback the active database transaction.
     *
     * @return bool
     */
    public function rollback()
    {
        return $this->driver->rollbackTransaction();
    }

    /**
     * Get every available database Table abstraction.
     *
     * @return Table[]
     */
    public function getTables()
    {
        $result = [];
        foreach ($this->driver->tableNames() as $table)
        {
            if ($this->tablePrefix && strpos($table, $this->tablePrefix) !== 0)
            {
                //Logical partitioning
                continue;
            }

            $result[] = $this->table(substr($table, strlen($this->tablePrefix)));
        }

        return $result;
    }

    /**
     * Check if table exists.
     *
     * @param string $name
     * @return bool
     */
    public function hasTable($name)
    {
        return $this->driver->hasTable($this->tablePrefix . $name);
    }

    /**
     * Get Table abstraction.
     *
     * @param string $name Table name without prefix.
     * @return Table
     */
    public function table($name)
    {
        return new Table($this, $name);
    }

    /**
     * Shortcut to get table abstraction.
     *
     * @param string $name Table name without prefix.
     * @return Table
     */
    public function __get($name)
    {
        return $this->table($name);
    }
}