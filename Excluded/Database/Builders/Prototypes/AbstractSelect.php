<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Builders\Prototypes;

use Spiral\Cache\StoreInterface;
use Spiral\Database\Builders\Traits\JoinsTrait;
use Spiral\Database\Entities\QueryBuilder;
use Spiral\Database\Entities\QueryCompiler;
use Spiral\Database\Exceptions\BuilderException;
use Spiral\Database\Exceptions\QueryException;
use Spiral\Database\Injections\ExpressionInterface;
use Spiral\Database\Injections\FragmentInterface;
use Spiral\Database\Injections\Parameter;
use Spiral\Database\Injections\ParameterInterface;
use Spiral\Database\Query\CachedResult;
use Spiral\Database\Query\PDOQuery;
use Spiral\Pagination\PaginatorAwareInterface;
use Spiral\Pagination\Traits\LimitsTrait;
use Spiral\Pagination\Traits\PaginatorTrait;

/**
 * Prototype for select queries, include ability to cache, paginate or chunk results. Support WHERE,
 * JOIN, HAVING, ORDER BY, GROUP BY, UNION and DISTINCT statements. In addition only desired set
 * of columns can be selected. In addition select.
 *
 * @see AbstractWhere
 *
 * @method int avg($identifier) Perform aggregation (AVG) based on column or expression value.
 * @method int min($identifier) Perform aggregation (MIN) based on column or expression value.
 * @method int max($identifier) Perform aggregation (MAX) based on column or expression value.
 * @method int sum($identifier) Perform aggregation (SUM) based on column or expression value.
 */
abstract class AbstractSelect extends AbstractWhere implements
    \IteratorAggregate,
    \JsonSerializable,
    PaginatorAwareInterface
{
    use JoinsTrait, LimitsTrait, PaginatorTrait;

    /**
     * Sort directions.
     */
    const SORT_ASC  = 'ASC';
    const SORT_DESC = 'DESC';

    /**
     * Query must return only unique rows.
     *
     * @var bool|string
     */
    protected $distinct = false;

    /**
     * Columns or expressions to be fetched from database, can include aliases (AS).
     *
     * @var array
     */
    protected $columns = ['*'];

    /**
     * Set of generated having tokens, format must be supported by QueryCompilers.
     *
     * @see AbstractWhere
     *
     * @var array
     */
    protected $havingTokens = [];

    /**
     * Parameters collected while generating HAVING tokens, must be in a same order as parameters
     * in resulted query.
     *
     * @see AbstractWhere
     *
     * @var array
     */
    protected $havingParameters = [];

    /**
     * Columns/expression associated with their sort direction (ASK|DESC).
     *
     * @var array
     */
    protected $ordering = [];

    /**
     * Columns/expressions to group by.
     *
     * @var array
     */
    protected $grouping = [];

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
        if (empty($compiler)) {
            //Using associated compiler
            $compiler = $this->compiler;
        }

        return $this->flattenParameters(
            $compiler->orderParameters(
                QueryCompiler::SELECT_QUERY,
                $this->whereParameters,
                $this->onParameters,
                $this->havingParameters
            )
        );
    }

    /**
     * Mark query to return only distinct results.
     *
     * @param bool|string $distinct You are only allowed to use string value for Postgres databases.
     *
     * @return $this
     */
    public function distinct($distinct = true)
    {
        $this->distinct = $distinct;

        return $this;
    }

    /**
     * Simple HAVING condition with various set of arguments.
     *
     * @see AbstractWhere
     *
     * @param string|mixed $identifier Column or expression.
     * @param mixed        $variousA   Operator or value.
     * @param mixed        $variousB   Value, if operator specified.
     * @param mixed        $variousC   Required only in between statements.
     *
     * @return $this
     *
     * @throws BuilderException
     */
    public function having($identifier, $variousA = null, $variousB = null, $variousC = null)
    {
        $this->whereToken('AND', func_get_args(), $this->havingTokens, $this->havingWrapper());

        return $this;
    }

    /**
     * Simple AND HAVING condition with various set of arguments.
     *
     * @see AbstractWhere
     *
     * @param string|mixed $identifier Column or expression.
     * @param mixed        $variousA   Operator or value.
     * @param mixed        $variousB   Value, if operator specified.
     * @param mixed        $variousC   Required only in between statements.
     *
     * @return $this
     *
     * @throws BuilderException
     */
    public function andHaving($identifier, $variousA = null, $variousB = null, $variousC = null)
    {
        $this->whereToken('AND', func_get_args(), $this->havingTokens, $this->havingWrapper());

        return $this;
    }

    /**
     * Simple OR HAVING condition with various set of arguments.
     *
     * @see AbstractWhere
     *
     * @param string|mixed $identifier Column or expression.
     * @param mixed        $variousA   Operator or value.
     * @param mixed        $variousB   Value, if operator specified.
     * @param mixed        $variousC   Required only in between statements.
     *
     * @return $this
     *
     * @throws BuilderException
     */
    public function orHaving($identifier, $variousA = [], $variousB = null, $variousC = null)
    {
        $this->whereToken('OR', func_get_args(), $this->havingTokens, $this->havingWrapper());

        return $this;
    }

    /**
     * Sort result by column/expression. You can apply multiple sortings to query via calling method
     * few times or by specifying values using array of sort parameters:.
     *
     * $select->orderBy([
     *      'id'   => SelectQuery::SORT_DESC,
     *      'name' => SelectQuery::SORT_ASC
     * ]);
     *
     * @param string|array $expression
     * @param string       $direction Sorting direction, ASC|DESC.
     *
     * @return $this
     */
    public function orderBy($expression, $direction = self::SORT_ASC)
    {
        if (!is_array($expression)) {
            $this->ordering[] = [$expression, $direction];

            return $this;
        }

        foreach ($expression as $nested => $direction) {
            $this->ordering[] = [$nested, $direction];
        }

        return $this;
    }

    /**
     * Column or expression to group query by.
     *
     * @param string $expression
     *
     * @return $this
     */
    public function groupBy($expression)
    {
        $this->grouping[] = $expression;

        return $this;
    }

    /**
     * Mark selection as cached one, result will be passed thought database->cached() method and
     * will be stored in cache storage for specified amount of seconds.
     *
     * @see Database::cached()
     *
     * @param int            $lifetime Cache lifetime in seconds.
     * @param string         $key      Optional, Database will generate key based on query.
     * @param StoreInterface $store    Optional, Database will resolve cache store using container.
     *
     * @return $this
     */
    public function cache(int $lifetime, string $key = '', StoreInterface $store = null)
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
     *
     * @return PDOQuery|CachedResult
     */
    public function run(bool $paginate = true)
    {
        if ($paginate && $this->hasPaginator()) {
            /**
             * To prevent original select builder altering
             *
             * @var AbstractSelect $select
             */
            $select = clone $this;

            //Getting selection specific paginator
            $paginator = $this->configurePaginator($this->count());

            //We have to ensure that selection works inside given pagination window
            $select = $select->limit(min($this->getLimit(), $paginator->getLimit()));

            //Making sure that window is shifted
            $select = $select->offset($this->getOffset() + $paginator->getOffset());

            //No inner pagination
            return $select->run(false);
        }

        if (!empty($this->cacheLifetime)) {
            //Cached query
            return $this->database->cached(
                $this->cacheLifetime,
                $this->sqlStatement(),
                $this->getParameters(),
                $this->cacheKey,
                $this->cacheStore
            );
        }

        return $this->database->query($this->sqlStatement(), $this->getParameters());
    }

    /**
     * Iterate thought result using smaller data chinks with defined size and walk function.
     *
     * Example:
     * $select->chunked(100, function(QueryResult $result, $offset, $count) {
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
        //TODO: Think about it

        $count = $this->count();

        $this->limit($limit);

        $offset = 0;

        while ($offset + $limit <= $count) {
            $result = call_user_func_array($callback, [
                $this->offset($offset)->getIterator(),
                $offset,
                $count,
            ]);

            if ($result === false) {
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
     *
     * @return int
     */
    public function count($column = '*')
    {
        /**
         * @var AbstractSelect $select
         */
        $select = clone $this;
        $select->columns = ["COUNT({$column})"];
        $select->ordering = [];
        $select->grouping = [];

        return (int)$select->run(false)->fetchColumn();
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
     *
     * @return int
     *
     * @throws BuilderException
     * @throws QueryException
     */
    public function __call($method, $arguments)
    {
        if (!in_array($method = strtoupper($method), ['AVG', 'MIN', 'MAX', 'SUM'])) {
            throw new BuilderException("Unknown method '{$method}' in '" . get_class($this) . "'");
        }

        if (!isset($arguments[0]) || count($arguments) > 1) {
            throw new BuilderException('Aggregation methods can support exactly one column');
        }

        /**
         * @var AbstractSelect $select
         */
        $select = clone $this;
        $select->columns = ["{$method}({$arguments[0]})"];

        return (int)$this->run(false)->fetchColumn();
    }

    /**
     * {@inheritdoc}
     *
     * @return PDOQuery
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

    /**
     * Destructing.
     */
    public function __destruct()
    {
        $this->paginator = null;
    }

    /**
     * Applied to every potential parameter while having tokens generation.
     *
     * @return \Closure
     */
    private function havingWrapper()
    {
        return function ($parameter) {
            if ($parameter instanceof FragmentInterface) {
                //We are only not creating bindings for plan fragments
                if (!$parameter instanceof ParameterInterface && !$parameter instanceof QueryBuilder) {
                    return $parameter;
                }
            }

            if (is_array($parameter)) {
                throw new BuilderException('Arrays must be wrapped with Parameter instance');
            }

            //Wrapping all values with ParameterInterface
            if (!$parameter instanceof ParameterInterface && !$parameter instanceof ExpressionInterface) {
                $parameter = new Parameter($parameter, Parameter::DETECT_TYPE);
            };

            //Let's store to sent to driver when needed
            $this->havingParameters[] = $parameter;

            return $parameter;
        };
    }
}
