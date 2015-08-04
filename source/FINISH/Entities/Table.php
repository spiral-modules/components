<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Entities;

use Spiral\Database\Builders\DeleteQuery;
use Spiral\Database\Builders\SelectQuery;
use Spiral\Database\Builders\UpdateQuery;
use Spiral\Database\Query\QueryResult;
use Spiral\Database\TableInterface;

/**
 * @method SelectQuery distinct(bool $distinct = true)
 * @method SelectQuery columns(mixed $columns)
 * @method SelectQuery groupBy(string $identifier)
 * @method SelectQuery orderBy(string $identifier, string $direction = 'ASC')
 * @method SelectQuery cache(int $lifetime)
 * @method QueryResult run(bool $paginate = true)
 * @method SelectQuery limit(int $limit = 0)
 * @method SelectQuery offset(int $offset = 0)
 * @method SelectQuery paginate($limit = 50, $pageParameter = 'page', $fetchCount = true, $request = null)
 * @method SelectQuery where($identifier, $variousA = null, $variousB = null, $variousC = null)
 * @method SelectQuery andWhere($identifier, $variousA = null, $variousB = null, $variousC = null)
 * @method SelectQuery orWhere($identifier, $variousA = [], $variousB = null, $variousC = null)
 * @method SelectQuery join(string $table, mixed $on = null)
 * @method SelectQuery innerJoin(string $table, mixed $on = null)
 * @method SelectQuery rightJoin(string $table, mixed $on = null)
 * @method SelectQuery leftJoin(string $table, mixed $on = null)
 * @method int avg($identifier) Perform aggregation based on column or expression value.
 * @method int min($identifier) Perform aggregation based on column or expression value.
 * @method int max($identifier) Perform aggregation based on column or expression value.
 * @method int sum($identifier) Perform aggregation based on column or expression value.
 */
class Table implements \JsonSerializable, \IteratorAggregate, TableInterface
{
    /**
     * Table name, without prefix.
     *
     * @var string
     */
    protected $name = '';

    /**
     * Parent DBAL database.
     *
     * @var Database
     */
    protected $database = null;

    /**
     * DBAL Table instance is helper class used to aggregate common table operation together, such
     * as select, update, insert and delete. Additionally it can be used to request table schema.
     *
     * @param string   $name     Table name without prefix.
     * @param Database $database Parent DBAL database.
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
        $this->database->driver()->truncateTable($this->name);
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