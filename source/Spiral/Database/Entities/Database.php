<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Entities;

use Spiral\Cache\StoreInterface;
use Spiral\Core\Container\InjectableInterface;
use Spiral\Database\Builders\DeleteQuery;
use Spiral\Database\Builders\InsertQuery;
use Spiral\Database\Builders\SelectQuery;
use Spiral\Database\Builders\UpdateQuery;
use Spiral\Database\DatabaseInterface;
use Spiral\Database\DatabaseManager;
use Spiral\Database\Exceptions\DatabaseException;
use Spiral\Database\Exceptions\DriverException;
use Spiral\Database\Exceptions\QueryException;
use Spiral\Database\Query\CachedResult;
use Spiral\Database\Query\PDOQuery;
use Spiral\Database\ResultInterface;

/**
 * Database class is high level abstraction at top of Driver. Multiple databases can use same driver
 * and use different by table prefix. Databases usually linked to real database or logical portion
 * of database (filtered by prefix).
 */
class Database implements DatabaseInterface, InjectableInterface
{
    /**
     * This is magick constant used by Spiral Container, it helps system to resolve controllable
     * injections.
     */
    const INJECTOR = DatabaseManager::class;

    /**
     * Transaction isolation level 'SERIALIZABLE'.
     *
     * This is the highest isolation level. With a lock-based concurrency control DBMS
     * implementation, serializability requires read and write locks (acquired on selected data) to
     * be released at the end of the transaction. Also range-locks must be acquired when a SELECT
     * query uses a ranged WHERE clause, especially to avoid the phantom reads phenomenon (see
     * below).
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
     * write locks (acquired on selected data) until the end of the transaction. However,
     * range-locks are not managed, so phantom reads can occur.
     *
     * @link http://en.wikipedia.org/wiki/Isolation_(database_systems)
     */
    const ISOLATION_REPEATABLE_READ = 'REPEATABLE READ';

    /**
     * Transaction isolation level 'READ COMMITTED'.
     *
     * In this isolation level, a lock-based concurrency control DBMS implementation keeps write
     * locks
     * (acquired on selected data) until the end of the transaction, but read locks are released as
     * soon as the SELECT operation is performed (so the non-repeatable reads phenomenon can occur
     * in this isolation level, as discussed below). As in the previous level, range-locks are not
     * managed.
     *
     * Putting it in simpler words, read committed is an isolation level that guarantees that any
     * data read is committed at the moment it is read. It simply restricts the reader from seeing
     * any intermediate, uncommitted, 'dirty' read. It makes no promise whatsoever that if the
     * transaction re-issues the read, it will find the same data; data is free to change after it
     * is read.
     *
     * @link http://en.wikipedia.org/wiki/Isolation_(database_systems)
     */
    const ISOLATION_READ_COMMITTED = 'READ COMMITTED';

    /**
     * Transaction isolation level 'READ UNCOMMITTED'.
     *
     * This is the lowest isolation level. In this level, dirty reads are allowed, so one
     * transaction may see not-yet-committed changes made by other transactions.
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
     * @var string
     */
    private $name = '';

    /**
     * @var string
     */
    private $prefix = '';

    /**
     * @var Driver
     */
    private $driver = null;

    /**
     * @invisible
     *
     * @var StoreInterface
     */
    private $cacheStore = null;

    /**
     * @param string         $name       Internal database name/id.
     * @param string         $prefix     Default database table prefix, will be used for all table
     *                                   identifiers.
     * @param Driver         $driver     Driver instance responsible for database connection.
     * @param StoreInterface $cache      Cache store associated with database.
     */
    public function __construct($name, $prefix = '', Driver $driver, StoreInterface $cache = null)
    {
        $this->name = $name;
        $this->setPrefix($prefix);

        $this->driver = $driver;

        //Not required
        $this->cacheStore = $cache;
    }


    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return $this->driver->getType();
    }

    /**
     * @return Driver
     */
    public function driver(): Driver
    {
        return $this->driver;
    }

    /**
     * @param string $prefix
     *
     * @todo with prefix?
     * @return self
     */
    public function setPrefix(string $prefix): Database
    {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * @return string
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(string $query, array $parameters = []): int
    {
        return $this->statement($query, $parameters)->rowCount();
    }

    /**
     * {@inheritdoc}
     *
     * @param string $class Class to be used to represent PDOStatement.
     * @param array  $args  Class construction arguments, by default array of parameters.
     *
     * @return ResultInterface|\PDOStatement|PDOQuery
     */
    public function query(
        string $query,
        array $parameters = [],
        string $class = null,
        array $args = []
    ): ResultInterface {
        return $this->driver->query($query, $parameters, $class, $args);
    }

    /**
     * Get instance of PDOStatement from Driver.
     *
     * @param string $query
     * @param array  $parameters Parameters to be binded into query.
     *
     * @return \PDOStatement
     *
     * @throws DriverException
     * @throws QueryException
     */
    public function statement(string $query, array $parameters = []): \PDOStatement
    {
        return $this->driver->statement($query, $parameters);
    }

    /**
     * Execute statement or fetch result from cache and return cached query iterator.
     *
     * @param int            $lifetime   Cache lifetime in seconds.
     * @param string         $query
     * @param array          $parameters Parameters to be binded into query.
     * @param string         $key        Cache key to be used to store query result.
     * @param StoreInterface $store      Cache store to store result in, if null default store will
     *                                   be used.
     *
     * @return CachedResult
     *
     * @throws DriverException
     * @throws QueryException
     */
    public function cached(
        int $lifetime,
        string $query,
        array $parameters = [],
        string $key = '',
        StoreInterface $store = null
    ) {
        if (empty($store)) {
            if (empty($this->cacheStore)) {
                throw new DatabaseException("StoreInterface is missing");
            }

            $store = $this->cacheStore;
        }

        if (empty($key)) {
            //Trying to build unique query id based on provided options and environment.
            $key = md5(serialize([$query, $parameters, $this->name, $this->prefix]));
        }

        $data = $store->remember($key, $lifetime, function () use ($query, $parameters) {
            return $this->query($query, $parameters)->fetchAll();
        });

        return new CachedResult($data, $parameters, $query, $key, $store);
    }

    /**
     * Get instance of InsertBuilder associated with current Database.
     *
     * @param string $table Table where values should be inserted to.
     *
     * @return InsertQuery
     */
    public function insert(string $table = ''): InsertQuery
    {
        return $this->driver->insertBuilder($this, compact('table'));
    }

    /**
     * Get instance of UpdateBuilder associated with current Database.
     *
     * @param string $table  Table where rows should be updated in.
     * @param array  $values Initial set of columns to update associated with their values.
     * @param array  $where  Initial set of where rules specified as array.
     *
     * @return UpdateQuery
     */
    public function update(string $table = '', array $values = [], array $where = []): UpdateQuery
    {
        return $this->driver->updateBuilder($this, compact('table', 'where', 'values'));
    }

    /**
     * Get instance of DeleteBuilder associated with current Database.
     *
     * @param string $table Table where rows should be deleted from.
     * @param array  $where Initial set of where rules specified as array.
     *
     * @return DeleteQuery
     */
    public function delete(string $table = '', array $where = []): DeleteQuery
    {
        return $this->driver->deleteBuilder($this, compact('table', 'where'));
    }

    /**
     * Get instance of SelectBuilder associated with current Database.
     *
     * @param array|string $columns Columns to select.
     *
     * @return SelectQuery
     */
    public function select($columns = '*'): SelectQuery
    {
        $columns = func_get_args();
        if (is_array($columns) && isset($columns[0]) && is_array($columns[0])) {
            //Can be required in some cases while collecting data from Table->select(), stupid bug.
            $columns = $columns[0];
        }

        return $this->driver->selectBuilder($this, ['columns' => $columns]);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $isolationLevel
     *
     * @throws \Exception
     */
    public function transaction(callable $callback, $isolationLevel = null)
    {
        $this->begin($isolationLevel);

        try {
            $result = call_user_func($callback, $this);
            $this->commit();

            return $result;
        } catch (\Exception $e) {
            $this->rollBack();
            throw $e;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     *
     * @link http://en.wikipedia.org/wiki/Isolation_(database_systems)
     *
     * @param string $isolationLevel
     */
    public function begin(string $isolationLevel = null)
    {
        return $this->driver->beginTransaction($isolationLevel);
    }

    /**
     * {@inheritdoc}
     */
    public function commit()
    {
        return $this->driver->commitTransaction();
    }

    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        return $this->driver->rollbackTransaction();
    }

    /**
     * {@inheritdoc}
     */
    public function hasTable(string $name): bool
    {
        return $this->driver->hasTable($this->prefix . $name);
    }

    /**
     * {@inheritdoc}
     *
     * @return Table
     */
    public function table(string $name): Table
    {
        return new Table($this, $name);
    }

    /**
     * {@inheritdoc}
     *
     * @return Table[]
     */
    public function getTables(): array
    {
        $result = [];
        foreach ($this->driver->tableNames() as $table) {
            if ($this->prefix && strpos($table, $this->prefix) !== 0) {
                //Logical partitioning
                continue;
            }

            $result[] = $this->table(substr($table, strlen($this->prefix)));
        }

        return $result;
    }

    /**
     * Shortcut to get table abstraction.
     *
     * @param string $name Table name without prefix.
     *
     * @return Table
     */
    public function __get($name): Table
    {
        return $this->table($name);
    }
}
