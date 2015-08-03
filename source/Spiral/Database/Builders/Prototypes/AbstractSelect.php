<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Builders\Prototypes;

use Spiral\Cache\StoreInterface;
use Spiral\Database\Builders\Traits\HavingTrait;
use Spiral\Database\Builders\Traits\JoinsTrait;
use Spiral\Database\Entities\QueryCompiler;
use Spiral\Database\Exceptions\BuilderException;
use Spiral\Database\Exceptions\QueryException;
use Spiral\Database\Query\CachedResult;
use Spiral\Database\Query\QueryResult;
use Spiral\Pagination\PaginableInterface;
use Spiral\Pagination\Traits\PaginatorTrait;

/**
 * Prototype for select queries, include ability to cache, paginate or chunk results. Support WHERE,
 * JOIN, HAVING, ORDER BY, GROUP BY, UNION and DISTINCT statements. In addition only desired set
 * of columns can be selected. In addition select
 *
 * @see AbstractWhere
 * @method int avg($identifier) Perform aggregation (AVG) based on column or expression value.
 * @method int min($identifier) Perform aggregation (MIN) based on column or expression value.
 * @method int max($identifier) Perform aggregation (MAX) based on column or expression value.
 * @method int sum($identifier) Perform aggregation (SUM) based on column or expression value.
 */
abstract class AbstractSelect extends AbstractWhere implements
    \Countable,
    \IteratorAggregate,
    PaginableInterface
{
    /**
     * Abstract select query must fully support joins, having and be paginable.
     */
    use JoinsTrait, HavingTrait, PaginatorTrait;

    /**
     * Sort directions.
     */
    const SORT_ASC  = 'ASC';
    const SORT_DESC = 'DESC';

    /**
     * Query must return only unique rows.
     *
     * @var bool
     */
    protected $distinct = false;

    /**
     * Columns or expressions to be fetched from database, can include aliases (AS).
     *
     * @var array
     */
    protected $columns = ['*'];

    /**
     * Columns/expression associated with their sort direction (ASK|DESC).
     *
     * @var array
     */
    protected $orderBy = [];

    /**
     * Columns/expressions to group by.
     *
     * @var array
     */
    protected $groupBy = [];

    /**
     * Associated cache store.
     *
     * @var StoreInterface
     */
    protected $cacheStore = null;

    /**
     * Cache lifetime in seconds.
     *
     * @var int
     */
    protected $cacheLifetime = 0;

    /**
     * User specified cache key (optional).
     *
     * @var string
     */
    protected $cacheKey = '';

    /**
     * {@inheritdoc}
     */
    public function getParameters(QueryCompiler $compiler = null)
    {
        $compiler = !empty($compiler) ? $compiler : $this->compiler;

        return $this->flattenParameters($compiler->prepareParameters(
            QueryCompiler::SELECT_QUERY,
            $this->whereParameters,
            $this->onParameters,
            $this->havingParameters
        ));
    }

    /**
     * Mark query to return only distinct results.
     *
     * @param bool|string $distinct You are only allowed to use string value for Postgres databases.
     * @return $this
     */
    public function distinct($distinct = true)
    {
        $this->distinct = $distinct;

        return $this;
    }

    /**
     * Sort result by column/expression. You can apply multiple sortings to query via calling method
     * few times or by specifying values using array of sort parameters:
     *
     * $select->orderBy([
     *      'id'   => SelectQuery::SORT_DESC,
     *      'name' => SelectQuery::SORT_ASC
     * ]);
     *
     * @param string|array $expression
     * @param string       $direction Sorting direction, ASC|DESC.
     * @return $this
     */
    public function orderBy($expression, $direction = self::SORT_ASC)
    {
        if (!is_array($expression))
        {
            $this->orderBy[] = [$expression, $direction];

            return $this;
        }

        foreach ($expression as $nested => $direction)
        {
            $this->orderBy[] = [$nested, $direction];
        }

        return $this;
    }

    /**
     * Column or expression to group query by.
     *
     * @param string $expression
     * @return $this
     */
    public function groupBy($expression)
    {
        $this->groupBy[] = $expression;

        return $this;
    }

    /**
     * Mark selection as cached one, result will be passed thought database->cached() method and
     * will be stored in cache storage for specified amount of seconds.
     *
     * @see Database::cached()
     * @param int            $lifetime Cache lifetime in seconds.
     * @param string         $key      Optional, Database will generate key based on query.
     * @param StoreInterface $store    Optional, Database will resolve cache store using container.
     * @return $this
     */
    public function cache($lifetime, $key = '', StoreInterface $store = null)
    {
        $this->cacheLifetime = $lifetime;
        $this->cacheKey = $key;
        $this->cacheStore = $store;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @param bool $paginate Apply pagination to result, can be disabled in honor of count method.
     * @return QueryResult|CachedResult
     */
    public function run($paginate = true)
    {
        $backup = [$this->limit, $this->offset];

        if ($paginate)
        {
            $this->runPagination();
        }
        else
        {
            //We have to flush limit and offset values when pagination is not required.
            $this->limit = $this->offset = 0;
        }

        if (empty($this->cacheLifetime))
        {
            $result = $this->database->query($this->sqlStatement(), $this->getParameters());
        }
        else
        {
            $result = $this->database->cached(
                $this->cacheLifetime,
                $this->sqlStatement(),
                $this->getParameters(),
                $this->cacheKey,
                $this->cacheStore
            );
        }

        //Restoring limit and offset values
        list($this->limit, $this->offset) = $backup;

        return $result;
    }

    /**
     * Iterate thought result using smaller data chinks with defined size and walk function.
     *
     * Example:
     * $select->chunked(100, function(QueryResult $result, $offset, $count)
     * {
     *      dump($result);
     * });
     *
     * You must return FALSE from walk function to stop chunking.
     *
     * @param int      $limit
     * @param callable $callback
     */
    public function chunked($limit, callable $callback)
    {
        $count = $this->count();

        $this->limit($limit);
        $offset = 0;

        while ($offset + $limit <= $count)
        {
            $result = call_user_func($callback, $this->offset($offset)->getIterator(), $offset, $count);
            if ($result === false)
            {
                //Stop iteration
                return;
            }

            $offset += $limit;
        }
    }

    /**
     * {@inheritdoc}
     *
     * Count number of rows in query. Limit, offset, order by, group by values will be ignored. Do
     * not count united queries, or queries in complex joins.
     *
     * @param string $column Column to count by (every column by default).
     * @return int
     */
    public function count($column = '*')
    {
        $backup = [$this->columns, $this->orderBy, $this->groupBy, $this->limit, $this->offset];
        $this->columns = ["COUNT({$column})"];

        //Can not be used with COUNT()
        $this->orderBy = $this->groupBy = [];
        $this->limit = $this->offset = 0;

        $result = $this->run(false)->fetchColumn();
        list($this->columns, $this->orderBy, $this->groupBy, $this->limit, $this->offset) = $backup;

        return (int)$result;
    }

    /**
     * {@inheritdoc}
     *
     * Shortcut to execute one of aggregation methods (AVG, MAX, MIN, SUM) using method name as
     * reference.
     *
     * Example:
     * echo $select->sum('user.balance');
     *
     * @param string $method
     * @param string $arguments
     * @return int
     * @throws BuilderException
     * @throws QueryException
     */
    public function __call($method, $arguments)
    {
        if (!in_array($method = strtoupper($method), ['AVG', 'MIN', 'MAX', 'SUM']))
        {
            throw new BuilderException("Unknown aggregation method '{$method}'.");
        }

        if (!isset($arguments[0]) || count($arguments) > 1)
        {
            throw new BuilderException("Aggregation methods can support exactly one column.");
        }

        $columns = $this->columns;
        $this->columns = ["{$method}({$arguments[0]})"];
        $result = $this->run(false)->fetchColumn();
        $this->columns = $columns;

        return (int)$result;
    }

    /**
     * {@inheritdoc}
     *
     * @return QueryResult
     */
    public function getIterator()
    {
        return $this->run();
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return $this->getIterator()->jsonSerialize();
    }
}