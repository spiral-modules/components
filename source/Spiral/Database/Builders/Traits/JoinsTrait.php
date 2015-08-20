<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Builders\Traits;

use Spiral\Database\Exceptions\BuilderException;
use Spiral\Database\Injections\Parameter;
use Spiral\Database\Injections\ParameterInterface;
use Spiral\Database\Injections\SQLExpression;
use Spiral\Database\Injections\SQLFragmentInterface;
use Spiral\Database\Entities\QueryBuilder;

/**
 * Provides ability to generate QueryCompiler JOIN tokens including ON conditions and table/column
 * aliases.
 *
 * Simple joins (ON userID = users.id):
 * $select->join('LEFT', 'info', 'userID', 'users.id');
 * $select->leftJoin('info', 'userID', '=', 'users.id');
 * $select->rightJoin('info', ['userID' => 'users.id']);
 *
 * More complex ON conditions:
 * $select->leftJoin('info', function($select) {
 *      $select->on('userID', 'users.id')->orOn('userID', 'users.masterID');
 * });
 *
 * To specify on conditions outside join method use "on" methods.
 * $select->leftJoin('info')->on('userID', '=', 'users.id');
 *
 * On methods will only support conditions based on outer table columns. You can not use parametric
 * values here, use "on where" conditions instead.
 * $select->leftJoin('info')->on('userID', '=', 'users.id')->onWhere('value', 100);
 *
 * Arguments and syntax in "on" and "onWhere" conditions is identical to "where" method defined in
 * AbstractWhere.
 * Attention, "on" and "onWhere" conditions will be applied to last registered join only!
 *
 * You can also use table aliases and use them in conditions after:
 * $select->join('LEFT', 'info as i')->on('i.userID', 'users.id');
 * $select->join('LEFT', 'info as i', function($select) {
 *      $select->on('i.userID', 'users.id')->orOn('i.userID', 'users.masterID');
 * });
 *
 * @see AbstractWhere
 */
trait JoinsTrait
{
    /**
     * Name/id of last join, every ON and ON WHERE call will be associated with this join.
     *
     * @var string
     */
    private $activeJoin = null;

    /**
     * Set of join tokens with on and on where conditions associated, must be supported by
     * QueryCompilers.
     *
     * @var array
     */
    protected $joinTokens = [];

    /**
     * Parameters collected while generating ON WHERE tokens, must be in a same order as parameters
     * in resulted query. Parameters declared in ON methods will be converted into expressions and
     * will not be aggregated.
     *
     * @see AbstractWhere
     * @var array
     */
    protected $onParameters = [];

    /**
     * Register new JOIN with specified type with set of on conditions (linking one table to another,
     * no parametric on conditions allowed here).
     *
     * @param string $type  Join type. Allowed values, LEFT, RIGHT, INNER and etc.
     * @param string $table Joined table name (without prefix), may include AS statement.
     * @param mixed  $on    Simplified on definition linking table names (no parameters allowed) or
     *                      closure.
     * @return $this
     * @throws BuilderException
     */
    public function join($type, $table, $on = null)
    {
        $this->joinTokens[$this->activeJoin = $table] = ['type' => strtoupper($type), 'on' => []];

        return call_user_func_array([$this, 'on'], array_slice(func_get_args(), 2));
    }

    /**
     * Register new INNER JOIN with set of on conditions (linking one table to another, no parametric
     * on conditions allowed here).
     *
     * @link http://www.w3schools.com/sql/sql_join_inner.asp
     * @see  join()
     * @param string $table Joined table name (without prefix), may include AS statement.
     * @param mixed  $on    Simplified on definition linking table names (no parameters allowed) or
     *                      closure.
     * @return $this
     * @throws BuilderException
     */
    public function innerJoin($table, $on = null)
    {
        $this->joinTokens[$this->activeJoin = $table] = ['type' => 'INNER', 'on' => []];

        return call_user_func_array([$this, 'on'], array_slice(func_get_args(), 1));
    }

    /**
     * Register new RIGHT JOIN with set of on conditions (linking one table to another, no parametric
     * on conditions allowed here).
     *
     * @link http://www.w3schools.com/sql/sql_join_right.asp
     * @see  join()
     * @param string $table Joined table name (without prefix), may include AS statement.
     * @param mixed  $on    Simplified on definition linking table names (no parameters allowed) or
     *                      closure.
     * @return $this
     * @throws BuilderException
     */
    public function rightJoin($table, $on = null)
    {
        $this->joinTokens[$this->activeJoin = $table] = ['type' => 'RIGHT', 'on' => []];

        return call_user_func_array([$this, 'on'], array_slice(func_get_args(), 1));
    }

    /**
     * Register new LEFT JOIN with set of on conditions (linking one table to another, no parametric
     * on conditions allowed here).
     *
     * @link http://www.w3schools.com/sql/sql_join_left.asp
     * @see  join()
     * @param string $table Joined table name (without prefix), may include AS statement.
     * @param mixed  $on    Simplified on definition linking table names (no parameters allowed) or
     *                      closure.
     * @return $this
     * @throws BuilderException
     */
    public function leftJoin($table, $on = null)
    {
        $this->joinTokens[$this->activeJoin = $table] = ['type' => 'LEFT', 'on' => []];

        return call_user_func_array([$this, 'on'], array_slice(func_get_args(), 1));
    }

    /**
     * Register new FULL JOIN with set of on conditions (linking one table to another, no parametric
     * on conditions allowed here).
     *
     * @link http://www.w3schools.com/sql/sql_join_full.asp
     * @see  join()
     * @param string $table Joined table name (without prefix), may include AS statement.
     * @param mixed  $on    Simplified on definition linking table names (no parameters allowed) or
     *                      closure.
     * @return $this
     * @throws BuilderException
     */
    public function fullJoin($table, $on = null)
    {
        $this->joinTokens[$this->activeJoin = $table] = ['type' => 'FULL', 'on' => []];

        return call_user_func_array([$this, 'on'], array_slice(func_get_args(), 1));
    }

    /**
     * Simple ON condition with various set of arguments. Can only be used to link column values
     * together, no parametric values allowed.
     *
     * @param mixed $joined   Joined column name or expression.
     * @param mixed $operator Foreign column name, if operator specified.
     * @param mixed $outer    Foreign column name.
     * @return $this
     * @throws BuilderException
     */
    public function on($joined = null, $operator = null, $outer = null)
    {
        $this->whereToken(
            'AND', func_get_args(), $this->joinTokens[$this->activeJoin]['on'], $this->onWrapper()
        );

        return $this;
    }

    /**
     * Simple AND ON condition with various set of arguments. Can only be used to link column values
     * together, no parametric values allowed.
     *
     * @param mixed $joined   Joined column name or expression.
     * @param mixed $operator Foreign column name, if operator specified.
     * @param mixed $outer    Foreign column name.
     * @return $this
     * @throws BuilderException
     */
    public function andOn($joined = null, $operator = null, $outer = null)
    {
        $this->whereToken(
            'AND', func_get_args(), $this->joinTokens[$this->activeJoin]['on'], $this->onWrapper()
        );

        return $this;
    }

    /**
     * Simple OR ON condition with various set of arguments. Can only be used to link column values
     * together, no parametric values allowed.
     *
     * @param mixed $joined   Joined column name or expression.
     * @param mixed $operator Foreign column name, if operator specified.
     * @param mixed $outer    Foreign column name.
     * @return $this
     * @throws BuilderException
     */
    public function orOn($joined = null, $operator = null, $outer = null)
    {
        $this->whereToken(
            'AND', func_get_args(), $this->joinTokens[$this->activeJoin]['on'], $this->onWrapper()
        );

        return $this;
    }

    /**
     * Simple ON WHERE condition with various set of arguments. You can use parametric values in
     * such methods.
     *
     * @see AbstractWhere
     * @param string|mixed $joined   Joined column or expression.
     * @param mixed        $variousA Operator or value.
     * @param mixed        $variousB Value, if operator specified.
     * @param mixed        $variousC Required only in between statements.
     * @return $this
     * @throws BuilderException
     */
    public function onWhere($joined, $variousA = null, $variousB = null, $variousC = null)
    {
        $this->whereToken(
            'AND', func_get_args(), $this->joinTokens[$this->activeJoin]['on'],
            $this->whereWrapper()
        );

        return $this;
    }

    /**
     * Simple AND ON WHERE condition with various set of arguments. You can use parametric values in
     * such methods.
     *
     * @see AbstractWhere
     * @param string|mixed $joined   Joined column or expression.
     * @param mixed        $variousA Operator or value.
     * @param mixed        $variousB Value, if operator specified.
     * @param mixed        $variousC Required only in between statements.
     * @return $this
     * @throws BuilderException
     */
    public function andOnWhere($joined, $variousA = null, $variousB = null, $variousC = null)
    {
        $this->whereToken(
            'AND', func_get_args(), $this->joinTokens[$this->activeJoin]['on'],
            $this->whereWrapper()
        );

        return $this;
    }

    /**
     * Simple OR ON WHERE condition with various set of arguments. You can use parametric values in
     * such methods.
     *
     * @see AbstractWhere
     * @param string|mixed $joined   Joined column or expression.
     * @param mixed        $variousA Operator or value.
     * @param mixed        $variousB Value, if operator specified.
     * @param mixed        $variousC Required only in between statements.
     * @return $this
     * @throws BuilderException
     */
    public function orOnWhere($joined, $variousA = null, $variousB = null, $variousC = null)
    {
        $this->whereToken(
            'AND', func_get_args(), $this->joinTokens[$this->activeJoin]['on'],
            $this->whereWrapper()
        );

        return $this;
    }

    /**
     * Convert various amount of where function arguments into valid where token.
     *
     * @see AbstractWhere
     * @param string        $joiner     Boolean joiner (AND | OR).
     * @param array         $parameters Set of parameters collected from where functions.
     * @param array         $tokens     Array to aggregate compiled tokens. Reference.
     * @param \Closure|null $wrapper    Callback or closure used to wrap/collect every potential
     *                                  parameter.
     * @throws BuilderException
     */
    abstract protected function whereToken(
        $joiner,
        array $parameters,
        &$tokens = [],
        callable $wrapper
    );

    /**
     * Convert parameters used in JOIN ON statements into sql expressions.
     *
     * @return \Closure
     */
    private function onWrapper()
    {
        return function ($parameter) {
            if (!$parameter instanceof SQLFragmentInterface) {
                return new SQLExpression($parameter);
            }

            return $parameter;
        };
    }

    /**
     * Applied to every potential parameter while ON WHERE tokens generation.
     *
     * @return \Closure
     */
    private function whereWrapper()
    {
        return function ($parameter) {
            if (!$parameter instanceof ParameterInterface && is_array($parameter)) {
                $parameter = new Parameter($parameter);
            }

            if
            (
                $parameter instanceof SQLFragmentInterface
                && !$parameter instanceof ParameterInterface
                && !$parameter instanceof QueryBuilder
            ) {
                return $parameter;
            }

            $this->onParameters[] = $parameter;

            return $parameter;
        };
    }
}