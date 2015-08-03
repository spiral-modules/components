<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Interfaces\Entities;

use Spiral\Database\Exceptions\QueryException;
use Spiral\Database\Interfaces\Builders\DeleteBuilderInterface;
use Spiral\Database\Interfaces\Builders\SelectBuilderInterface;
use Spiral\Database\Interfaces\Builders\UpdateBuilderInterface;
use Spiral\Database\Interfaces\Query\ResultIteratorInterface;

/**
 * DatabaseInterface is high level abstraction used to represent single database.
 */
interface DatabaseInterface
{
    /**
     * @return string
     */
    public function getName();

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
     * @return ResultIteratorInterface
     * @throws QueryException
     */
    public function query($query, array $parameters = []);

       /**
     * Get instance of UpdateBuilderInterface associated with current Database.
     *
     * @param string $table
     * @param array  $values Initial set of columns to update associated with their values.
     * @param array  $where  Initial set of where rules specified as array.
     * @return UpdateBuilderInterface
     */
    public function update($table = '', array $values = [], array $where = []);

    /**
     * Get instance of DeleteBuilderInterface associated with current Database.
     *
     * @param string $table
     * @param array  $where Initial set of where rules specified as array.
     * @return DeleteBuilderInterface
     */
    public function delete($table = '', array $where = []);

    /**
     * Get instance of SelectBuilderInterface associated with current Database.
     *
     * @param array|string $columns Columns to select.
     * @return SelectBuilderInterface
     */
    public function select($columns = '*');

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
     * Get Table abstraction.
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