<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Database;

use Spiral\Database\Exceptions\QueryException;

/**
 * DatabaseInterface is high level abstraction used to represent single database. You MUST always
 * check database type using getType() method before writing SQL for execute and query methods.
 */
interface DatabaseInterface
{
    /**
     * Known database types.
     */
    const MYSQL      = 'MySQL';
    const POSTGRES   = 'Postgres';
    const SQLITE     = 'SQLite';
    const SQL_SERVER = 'SQLServer';

    /**
     * @return string
     */
    public function getName();

    /**
     * Database type matched to one of database constants. You MUST write SQL for execute and query
     * methods by respecting result of this method.
     *
     * @return string
     */
    public function getType();

    /**
     * Execute statement and return number of affected rows.
     *
     * @param string $query
     * @param array  $parameters Parameters to be binded into query.
     * @return int
     * @throws QueryException
     */
    public function execute($query, array $parameters = []);

    /**
     * Execute statement and return query iterator.
     *
     * @param string $query
     * @param array  $parameters Parameters to be binded into query.
     * @return ResultInterface
     * @throws QueryException
     */
    public function query($query, array $parameters = []);

    /**
     * Execute multiple commands defined by Closure function inside one transaction. Closure or
     * function must receive only one argument - DatabaseInterface instance.
     *
     * @param callable $callback
     * @return mixed
     * @throws \Exception
     */
    public function transaction(callable $callback);

    /**
     * Start database transaction.
     *
     * @link http://en.wikipedia.org/wiki/Database_transaction
     * @return bool
     */
    public function begin();

    /**
     * Commit the active database transaction.
     *
     * @return bool
     */
    public function commit();

    /**
     * Rollback the active database transaction.
     *
     * @return bool
     */
    public function rollback();

    /**
     * Check if table exists.
     *
     * @param string $name
     * @return bool
     */
    public function hasTable($name);

    /**
     * Get Table abstraction. Must return valid instance if table does not exists.
     *
     * @param string $name
     * @return TableInterface
     */
    public function table($name);

    /**
     * Get every available database Table abstraction.
     *
     * @return TableInterface[]
     */
    public function getTables();
}