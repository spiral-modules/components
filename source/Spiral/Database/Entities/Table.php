<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Entities;

use Psr\Http\Message\ServerRequestInterface;
use Spiral\Cache\StoreInterface;
use Spiral\Database\Builders\DeleteQuery;
use Spiral\Database\Builders\SelectQuery;
use Spiral\Database\Builders\UpdateQuery;
use Spiral\Database\Entities\Schemas\AbstractTable;
use Spiral\Database\Injections\SQLFragmentInterface;
use Spiral\Database\Query\QueryResult;
use Spiral\Database\TableInterface;
use Spiral\Pagination\PaginableInterface;

/**
 * Represent table level abstraction with simplified access to SelectQuery associated with such table.
 *
 * @method SelectQuery columns($columns)
 * @method SelectQuery union(SQLFragmentInterface $query)
 * @method SelectQuery unionAll(SQLFragmentInterface $query)
 *
 * @method SelectQuery distinct($distinct = 1)
 * @method SelectQuery having($identifier, $variousA = null, $variousB = null, $variousC = null)
 * @method SelectQuery andHaving($identifier, $variousA = null, $variousB = null, $variousC = null)
 * @method SelectQuery orHaving($identifier, $variousA = [], $variousB = null, $variousC = null)
 *
 * @method SelectQuery orderBy($expression, $direction = 'ASC')
 * @method SelectQuery groupBy($expression)
 *
 * @method SelectQuery cache($lifetime, $key = '', StoreInterface $store = null)
 *
 * @method QueryResult run($paginate = 1)
 * @method chunked($limit, $callback)
 *
 * @method SelectQuery where($identifier, $variousA = null, $variousB = null, $variousC = null)
 * @method SelectQuery andWhere($identifier, $variousA = null, $variousB = null, $variousC = null)
 * @method SelectQuery orWhere($identifier, $variousA = [], $variousB = null, $variousC = null)
 *
 * @method SelectQuery limit($limit = 0)
 * @method int getLimit()
 * @method SelectQuery offset($offset = 0)
 * @method int getOffset()
 *
 * @method SelectQuery paginate($limit = 25, $count = null, $pageParameter = 'page', ServerRequestInterface $request = null)
 * @method PaginableInterface getPaginator()
 *
 * @method SelectQuery join($type, $table, $on = null)
 * @method SelectQuery innerJoin($table, $on = null)
 * @method SelectQuery rightJoin($table, $on = null)
 * @method SelectQuery leftJoin($table, $on = null)
 * @method SelectQuery fullJoin($table, $on = null)
 *
 * @method SelectQuery on($joined = null, $operator = null, $outer = null)
 * @method SelectQuery andOn($joined = null, $operator = null, $outer = null)
 * @method SelectQuery orOn($joined = null, $operator = null, $outer = null)
 *
 * @method SelectQuery onWhere($joined, $variousA = null, $variousB = null, $variousC = null)
 * @method SelectQuery andOnWhere($joined, $variousA = null, $variousB = null, $variousC = null)
 * @method SelectQuery orOnWhere($joined, $variousA = null, $variousB = null, $variousC = null)
 *
 * @method int avg($identifier) Perform aggregation (AVG) based on column or expression value.
 * @method int min($identifier) Perform aggregation (MIN) based on column or expression value.
 * @method int max($identifier) Perform aggregation (MAX) based on column or expression value.
 * @method int sum($identifier) Perform aggregation (SUM) based on column or expression value.
 */
class Table implements \JsonSerializable, \IteratorAggregate, TableInterface
{
    /**
     * @var string
     */
    private $name = '';

    /**
     * @var Database
     */
    protected $database = null;

    /**
     * @param Database $database Parent DBAL database.
     * @param string   $name     Table name without prefix.
     */
    public function __construct(Database $database, $name)
    {
        $this->name = $name;
        $this->database = $database;
    }

    /**
     * {@inheritdoc}
     *
     * @return AbstractTable
     */
    public function schema()
    {
        return $this->database->driver()->tableSchema(
            $this->database->getPrefix() . $this->name,
            $this->database->getPrefix()
        );
    }

    /**
     * Check if table exists.
     *
     * @return bool
     */
    public function exists()
    {
        return $this->database->hasTable($this->name);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get list of column names associated with their abstract types.
     *
     * @return array
     */
    public function getColumns()
    {
        $columns = [];
        foreach ($this->schema()->getColumns() as $column)
        {
            $columns[$column->getName()] = $column->abstractType();
        }

        return $columns;
    }

    /**
     * {@inheritdoc}
     */
    public function truncate()
    {
        $this->database->driver()->truncate($this->database->getPrefix() . $this->name);
    }


    /**
     * {@inheritdoc}
     */
    public function insert(array $rowset = [])
    {
        return $this->database->insert($this->name)->values($rowset)->run();
    }

    /**
     * Perform batch insert into table, every rowset should have identical amount of values matched
     * with column names provided in first argument. Method will return lastInsertID on success.
     *
     * Example:
     * $table->insert(["name", "balance"], array(["Bob", 10], ["Jack", 20]))
     *
     * @param array $columns Array of columns.
     * @param array $rowsets Array of rowsets.
     * @return mixed
     */
    public function batchInsert(array $columns = [], array $rowsets = [])
    {
        return $this->database->insert($this->name)->columns($columns)->values($rowsets)->run();
    }

    /**
     * Get SelectQuery builder with pre-populated from tables.
     *
     * @param string $columns
     * @return SelectQuery
     */
    public function select($columns = '*')
    {
        return $this->database->select(func_num_args() ? func_get_args() : '*')->from($this->name);
    }

    /**
     * Get DeleteQuery builder with pre-populated table name. This is NOT table delete method, use
     * schema()->drop() for this purposes. If you want to remove all records from table use
     * Table->truncate() method. Call ->run() to perform query.
     *
     * @param array $where Initial set of where rules specified as array.
     * @return DeleteQuery
     */
    public function delete(array $where = [])
    {
        return $this->database->delete($this->name, $where);
    }

    /**
     * Get UpdateQuery builder with pre-populated table name and set of columns to update. Columns
     * can be scalar values, Parameter objects or even SQLFragments. Call ->run() to perform query.
     *
     * @param array $values Initial set of columns associated with values.
     * @param array $where  Initial set of where rules specified as array.
     * @return UpdateQuery
     */
    public function update(array $values = [], array $where = [])
    {
        return $this->database->update($this->name, $values, $where);
    }

    /**
     * Count number of records in table.
     *
     * @return int
     */
    public function count()
    {
        return $this->select()->count();
    }

    /**
     * Retrieve an external iterator, SelectBuilder will return QueryResult as iterator.
     *
     * @link http://php.net/manual/en/iteratoraggregate.getiterator.php
     * @return SelectQuery
     */
    public function getIterator()
    {
        return $this->select();
    }

    /**
     * A simple alias for table query without condition.
     *
     * @return QueryResult
     */
    public function all()
    {
        return $this->run();
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return $this->run()->jsonSerialize();
    }

    /**
     * Bypass call to SelectQuery builder.
     *
     * @param string $method
     * @param array  $arguments
     * @return SelectQuery
     */
    public function __call($method, array $arguments)
    {
        return call_user_func_array([$this->select(), $method], $arguments);
    }
}