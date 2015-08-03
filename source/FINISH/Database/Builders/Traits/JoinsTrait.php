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
use Spiral\Database\Injections\SQLExpression;
use Spiral\Database\Injections\SQLFragmentInterface;
use Spiral\Database\ParameterInterface;
use Spiral\Database\QueryBuilder;

/**
 * * Examples:
 * $select->join('LEFT', 'info', 'userID', 'users.id')->columns('info.balance');
 * $select->join('LEFT', 'info', 'userID', '=', 'users.id')->columns('info.balance');
 * $select->join('LEFT', 'info', ['userID' => 'users.id'])->columns('info.balance');
 *
 * $select->join('LEFT', 'info', function($select) {
 *      $select->on('userID', 'users.id')->orOn('userID', 'users.masterID');
 * })->columns('info.balance');
 *
 * Aliases can be also used:
 * $select->join('LEFT', 'info as i', 'i.userID', 'users.id')->columns('i.balance');
 * $select->join('LEFT', 'info as i', 'i.userID', '=', 'users.id')->columns('i.balance');
 * $select->join('LEFT', 'info as i', ['i.userID' => 'users.id'])->columns('i.balance');
 *
 * $select->join('LEFT', 'info as i', function($select) {
 *      $select->on('i.userID', 'users.id')->orOn('i.userID', 'users.masterID');
 * })->columns('i.balance');
 *
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
     * in resulted query.
     *
     * @var array
     */
    protected $onParameters = [];


    /**
     * Register new
     *
     * @param string $type  Join type. Allowed values, LEFT, RIGHT, INNER and etc.
     * @param string $table Joined table name (without prefix), can have defined alias.
     * @param mixed  $on    Where parameters, closure of array of where conditions.
     * @return $this
     * @throws BuilderException
     */
    public function join($type, $table, $on = null)
    {
        $this->joinTokens[$this->activeJoin = $table] = [
            'type' => strtoupper($type),
            'on'   => []
        ];

        return call_user_func_array([$this, 'on'], array_slice(func_get_args(), 2));
    }

    /**
     * Register new INNER table join, all future on() method calls will associate conditions to this
     * join.
     *
     * @link http://www.w3schools.com/sql/sql_join_inner.asp
     * @see  join()
     * @param string $table Joined table name (without prefix), can have defined alias.
     * @param mixed  $on    Where parameters, closure of array of where conditions.
     * @return $this
     * @throws BuilderException
     */
    public function innerJoin($table, $on = null)
    {
        $this->joinTokens[$this->activeJoin = $table] = [
            'type' => 'INNER',
            'on'   => []
        ];

        return call_user_func_array([$this, 'on'], array_slice(func_get_args(), 1));
    }

    /**
     * Register new RIGHT table join, all future on() method calls will associate conditions to this
     * join.
     *
     * Join aliases can be used in columns, where conditions, having conditions, order by, sort by
     * and aggregations.
     *
     * @link http://www.w3schools.com/sql/sql_join_right.asp
     * @see  join()
     * @param string $table Joined table name (without prefix), can have defined alias.
     * @param mixed  $on    Where parameters, closure of array of where conditions.
     * @return $this
     * @throws BuilderException
     */
    public function rightJoin($table, $on = null)
    {
        $this->joinTokens[$this->activeJoin = $table] = [
            'type' => 'RIGHT',
            'on'   => []
        ];

        return call_user_func_array([$this, 'on'], array_slice(func_get_args(), 1));
    }

    /**
     * Register new LEFT table join, all future on() method calls will associate conditions to this
     * join.
     *
     *
     * @link http://www.w3schools.com/sql/sql_join_left.asp
     * @see  join()
     * @param string $table Joined table name (without prefix), can have defined alias.
     * @param mixed  $on    Where parameters, closure of array of where conditions.
     * @return $this
     * @throws BuilderException
     */
    public function leftJoin($table, $on = null)
    {
        $this->joinTokens[$this->activeJoin = $table] = [
            'type' => 'LEFT',
            'on'   => []
        ];

        return call_user_func_array([$this, 'on'], array_slice(func_get_args(), 1));
    }

    /**
     * Register new FULL table join, all future on() method calls will associate conditions to this
     * join.
     *
     *
     * @link http://www.w3schools.com/sql/sql_join_full.asp
     * @see  join()
     * @param string $table Joined table name (without prefix), can have defined alias.
     * @param mixed  $on    Where parameters, closure of array of where conditions.
     * @return $this
     * @throws BuilderException
     */
    public function fullJoin($table, $on = null)
    {
        $this->joinTokens[$this->activeJoin = $table] = [
            'type' => 'FULL',
            'on'   => []
        ];

        return call_user_func_array([$this, 'on'], array_slice(func_get_args(), 1));
    }

    /**
     * @param mixed $on         Joined column name or SQLFragment, or where array.
     * @param mixed $operator   Foreign column is operator specified.
     * @param mixed $identifier Foreign column.
     * @return $this
     * @throws BuilderException
     */
    public function on($on = null, $operator = null, $identifier = null)
    {
        $this->whereToken(
            'AND',
            func_get_args(),
            $this->joinTokens[$this->activeJoin]['on'],
            $this->joinWrapper()
        );

        return $this;
    }

    /**
     * @param mixed $on         Joined column name or SQLFragment, or where array.
     * @param mixed $operator   Foreign column is operator specified.
     * @param mixed $identifier Foreign column.
     * @return $this
     * @throws BuilderException
     */
    public function andOn($on = null, $operator = null, $identifier = null)
    {
        $this->whereToken(
            'AND',
            func_get_args(),
            $this->joinTokens[$this->activeJoin]['on'],
            $this->joinWrapper()
        );

        return $this;
    }

    /**
     * @param mixed $on         Joined column name or SQLFragment, or where array.
     * @param mixed $operator   Foreign column is operator specified.
     * @param mixed $identifier Foreign column.
     * @return $this
     * @throws BuilderException
     */
    public function orOn($on = null, $operator = null, $identifier = null)
    {
        $this->whereToken(
            'AND',
            func_get_args(),
            $this->joinTokens[$this->activeJoin]['on'],
            $this->joinWrapper()
        );

        return $this;
    }

    /**
     * @param mixed $on       Joined column name or SQLFragment, or where array.
     * @param mixed $operator Foreign column is operator specified.
     * @param mixed $value    Value.
     * @return $this
     * @throws BuilderException
     */
    public function onWhere($on = null, $operator = null, $value = null)
    {
        $this->whereToken(
            'AND',
            func_get_args(),
            $this->joinTokens[$this->activeJoin]['on'],
            $this->onWrapper()
        );

        return $this;
    }

    /**
     * @param mixed $on       Joined column name or SQLFragment, or where array.
     * @param mixed $operator Foreign column is operator specified.
     * @param mixed $value    Value.
     * @return $this
     * @throws BuilderException
     */
    public function andOnWhere($on = null, $operator = null, $value = null)
    {
        $this->whereToken(
            'AND',
            func_get_args(),
            $this->joinTokens[$this->activeJoin]['on'],
            $this->onWrapper()
        );

        return $this;
    }

    /**
     * @param mixed $on       Joined column name or SQLFragment, or where array.
     * @param mixed $operator Foreign column is operator specified.
     * @param mixed $value    Value.
     * @return $this
     * @throws BuilderException
     */
    public function orOnWhere($on = null, $operator = null, $value = null)
    {
        $this->whereToken(
            'AND',
            func_get_args(),
            $this->joinTokens[$this->activeJoin]['on'],
            $this->onWrapper()
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
    abstract protected function whereToken($joiner, array $parameters, &$tokens = [], callable $wrapper);

    /**
     * Convert parameters used in JOIN ON statements into sql expressions.
     *
     * @return \Closure
     */
    private function joinWrapper()
    {
        return function ($parameter)
        {
            if (!$parameter instanceof SQLFragmentInterface)
            {
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
    private function onWrapper()
    {
        return function ($parameter)
        {
            if (!$parameter instanceof ParameterInterface && is_array($parameter))
            {
                $parameter = new Parameter($parameter);
            }

            if
            (
                $parameter instanceof SQLFragmentInterface
                && !$parameter instanceof ParameterInterface
                && !$parameter instanceof QueryBuilder
            )
            {
                return $parameter;
            }

            $this->onParameters[] = $parameter;

            return $parameter;
        };
    }
}