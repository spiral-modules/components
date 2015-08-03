<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Builders\Prototypes;

use Spiral\Database\Exceptions\BuilderException;
use Spiral\Database\Injections\Parameter;
use Spiral\Database\Injections\SQLFragmentInterface;
use Spiral\Database\Injections\ParameterInterface;
use Spiral\Database\QueryBuilder;

/**
 * Abstract query with WHERE conditions generation support. Provides simplified way to generate WHERE
 * tokens using set of where methods. Class support different where conditions, simplified definitions
 * (using arrays) and closures to describe nested conditions:
 *
 * 1) Simple token/nested query or expression
 * $select->where(new SQLFragment('(SELECT count(*) from `table`)'));
 *
 * 2) Simple assessment
 * $select->where('column', $value);
 * $select->where('column', new SQLFragment('CONCAT(columnA, columnB)'));
 *
 * 3) Assessment with specified operator (operator will be converted to uppercase automatically)
 * $select->where('column', '=', $value);
 * $select->where('column', 'IN', [1, 2, 3]);
 * $select->where('column', 'LIKE', $string);
 * $select->where('column', 'IN', new SQLFragment('(SELECT id from `table` limit 1)'));
 *
 * 4) Between and not between statements
 * $select->where('column', 'between', 1, 10);
 * $select->where('column', 'not between', 1, 10);
 * $select->where('column', 'not between', new SQLFragment('MIN(price)'), $maximum);
 *
 * 5) Closure with nested conditions
 * $this->where(function(AbstractWhere $select){
 *      $select->where("name", "Wolfy-J")->orWhere("balance", ">", 100)
 * });
 *
 * 6) Simplified array based condition definition
 * $select->where(["column" => 1]);
 * $select->where(["column" => [
 *      ">" => 1,
 *      "<" => 10
 * ]]);
 *
 * Tokens "@or" and "@and" used to aggregate nested conditions.
 * $select->where([
 *      "@or" => [
 *          ["id" => 1],
 *          ["column" => ["like" => "name"]]
 *      ]
 * ]);
 *
 * $select->where([
 *      "@or" => [
 *          ["id" => 1], ["id" => 2], ["id" => 3], ["id" => 4], ["id" => 5]
 *      ],
 *      "column" => [
 *          "like" => "name"
 *      ],
 *      "x" => [
 *          ">" => 1,
 *          "<" => 10
 *      ]
 * ]);
 *
 * To describe between or not between condition use array with two arguments.
 * $select->where([
 *      "column" => [
 *          "between" => [1, 100]
 *      ]
 * ]);
 */
abstract class AbstractWhere extends QueryBuilder
{
    /**
     * Tokens for nested OR and AND conditions.
     */
    const TOKEN_AND = "@AND";
    const TOKEN_OR  = "@OR";

    /**
     * Set of generated where tokens, format must be supported by QueryCompilers.
     *
     * @var array
     */
    protected $whereTokens = [];

    /**
     * Parameters collected while generating WHERE tokens, must be in a same order as parameters
     * in resulted query.
     *
     * @var array
     */
    protected $whereParameters = [];

    /**
     * Simple WHERE condition with various set of arguments.
     *
     * @see AbstractWhere
     * @param string|mixed $identifier Column or expression.
     * @param mixed        $variousA   Operator or value.
     * @param mixed        $variousB   Value, if operator specified.
     * @param mixed        $variousC   Required only in between statements.
     * @return $this
     * @throws BuilderException
     */
    public function where($identifier, $variousA = null, $variousB = null, $variousC = null)
    {
        $this->whereToken('AND', func_get_args(), $this->whereTokens, $this->whereWrapper());

        return $this;
    }

    /**
     * Simple AND WHERE condition with various set of arguments.
     *
     * @see AbstractWhere
     * @param string|mixed $identifier Column or expression.
     * @param mixed        $variousA   Operator or value.
     * @param mixed        $variousB   Value, if operator specified.
     * @param mixed        $variousC   Required only in between statements.
     * @return $this
     * @throws BuilderException
     */
    public function andWhere($identifier, $variousA = null, $variousB = null, $variousC = null)
    {
        $this->whereToken('AND', func_get_args(), $this->whereTokens, $this->whereWrapper());

        return $this;
    }

    /**
     * Simple OR WHERE condition with various set of arguments.
     *
     * @see AbstractWhere
     * @param string|mixed $identifier Column or expression.
     * @param mixed        $variousA   Operator or value.
     * @param mixed        $variousB   Value, if operator specified.
     * @param mixed        $variousC   Required only in between statements.
     * @return $this
     * @throws BuilderException
     */
    public function orWhere($identifier, $variousA = [], $variousB = null, $variousC = null)
    {
        $this->whereToken('OR', func_get_args(), $this->whereTokens, $this->whereWrapper());

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
    protected function whereToken($joiner, array $parameters, &$tokens = [], callable $wrapper)
    {
        list($identifier, $valueA, $valueB, $valueC) = $parameters + array_fill(0, 5, null);

        if (empty($identifier))
        {
            //Nothing to do
            return;
        }

        if (is_array($identifier))
        {
            $tokens[] = [$joiner, '('];
            $this->parseSimplified(self::TOKEN_AND, $identifier, $tokens, $wrapper);
            $tokens[] = ['', ')'];

            return;
        }

        if ($identifier instanceof \Closure)
        {
            $tokens[] = [$joiner, '('];
            call_user_func($identifier, $this, $joiner, $wrapper);
            $tokens[] = ['', ')'];

            return;
        }

        if ($identifier instanceof QueryBuilder)
        {
            //This will copy every parameter from QueryBuilder
            $wrapper($identifier);
        }

        switch (count($parameters))
        {
            case 1:
                //AND|OR [identifier: sub-query]
                $tokens[] = [$joiner, $identifier];
                break;
            case 2:
                //AND|OR [identifier] = [valueA]
                $tokens[] = [$joiner, [$identifier, '=', $wrapper($valueA)]];
                break;
            case 3:
                //AND|OR [identifier] [valueA: OPERATION] [valueA]
                $tokens[] = [$joiner, [$identifier, strtoupper($valueA), $wrapper($valueB)]];
                break;
            case 4:
                //BETWEEN or NOT BETWEEN
                $valueA = strtoupper($valueA);
                if (!in_array($valueA, ['BETWEEN', 'NOT BETWEEN']))
                {
                    throw new BuilderException(
                        'Only "BETWEEN" or "NOT BETWEEN" can define second comparasions value.'
                    );
                }

                //AND|OR [identifier] [valueA: BETWEEN|NOT BETWEEN] [valueB] [valueC]
                $tokens[] = [$joiner, [$identifier, $valueA, $wrapper($valueB), $wrapper($valueC)]];
        }
    }

    /**
     * Convert simplified where definition into valid set of where tokens.
     *
     * @see AbstractWhere
     * @param string   $grouper         Grouper type (see self::TOKEN_AND, self::TOKEN_OR).
     * @param array    $where           Simplified where definition.
     * @param array    $tokens          Array to aggregate compiled tokens. Reference.
     * @param \Closure $wrapper         Callback or closure used to wrap/collect every potential
     *                                  parameter.
     * @throws BuilderException
     */
    private function parseSimplified($grouper, array $where, &$tokens, callable $wrapper)
    {
        $joiner = $grouper == self::TOKEN_AND ? 'AND' : 'OR';
        foreach ($where as $name => $value)
        {
            $token = strtoupper($name);

            //Grouping identifier (@OR, @AND), MongoDB like style
            if ($token == self::TOKEN_AND || $token == self::TOKEN_OR)
            {
                //Open group
                $tokens[] = [$joiner, '('];

                foreach ($value as $nestedWhere)
                {
                    $this->parseSimplified($token, $nestedWhere, $tokens, $wrapper);
                }

                $tokens[] = ['', ')'];
                continue;
            }

            if (!is_array($value))
            {
                //AND|OR [name] = [value]
                $tokens[] = [$joiner, [$name, '=', $wrapper($value)]];
                continue;
            }

            $innerJoiner = $joiner;
            if (count($value) > 1)
            {
                $tokens[] = [$joiner, '('];

                //Multiple values to be joined by AND condition (x = 1, x != 5)
                $innerJoiner = 'AND';
            }

            foreach ($value as $key => $nestedValue)
            {
                if (is_numeric($key))
                {
                    throw new BuilderException("Nested conditions should have defined operator.");
                }

                $operation = strtoupper($key);
                if (!in_array($operation, ['BETWEEN', 'NOT BETWEEN']))
                {
                    //AND|OR [name] [OPERATION] [nestedValue]
                    $tokens[] = [$innerJoiner, [$name, $operation, $wrapper($nestedValue)]];
                    continue;
                }

                /**
                 * Between and not between condition described using array of [left, right] syntax.
                 */

                if (!is_array($nestedValue) || count($nestedValue) != 2)
                {
                    throw new BuilderException(
                        "Exactly 2 array values are required for between statement."
                    );
                }

                $tokens[] = [
                    //AND|OR [name] [BETWEEN|NOT BETWEEN] [value 1] [value 2]
                    $innerJoiner, [$name, $operation, $wrapper($nestedValue[0]), $wrapper($nestedValue[1])]
                ];
            }

            if (count($value) > 1)
            {
                $tokens[] = ['', ')'];
            }
        }

        return;
    }

    /**
     * Applied to every potential parameter while where tokens generation. Used to prepare and
     * collect where parameters.
     *
     * @return \Closure
     */
    private function whereWrapper()
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

            $this->whereParameters[] = $parameter;

            return $parameter;
        };
    }
}