<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Builders\Traits;

use Spiral\Database\DatabaseException;
use Spiral\Database\Parameter;
use Spiral\Database\ParameterInterface;
use Spiral\Database\QueryBuilder;
use Spiral\Database\SqlFragmentInterface;

trait HavingTrait
{
    /**
     * Array of having tokens declaring where conditions for HAVING statement. Structure and format
     * of this tokens are identical to whereTokens in WhereTrait.
     *
     * @see WhereTrait
     * @var array
     */
    protected $havingTokens = [];

    /**
     * Having parameters has to be stored separately from other query parameters as they have their
     * own order.
     *
     * @var array
     */
    protected $havingParameters = [];

    /**
     * Helper methods used to processed user input in where methods to internal where token, method
     * support all different combinations, closures and nested queries. Additionally i can be used
     * not only for where but for having and join tokens.
     *
     * @param string        $joiner     Boolean joiner (AND|OR).
     * @param array         $parameters Set of parameters collected from where functions.
     * @param array         $tokens     Array to aggregate compiled tokens.
     * @param \Closure|null $wrapper    Callback or closure used to handle all catched
     *                                  parameters, by default $this->addParameter will be used.
     * @return array
     * @throws DatabaseException
     */
    abstract protected function whereToken(
        $joiner,
        array $parameters,
        &$tokens = [],
        callable $wrapper = null
    );

    /**
     *
     * You can read more about complex where statements in official documentation or look mongo
     * queries examples.
     *
     * @see WhereTrait
     * @see parseWhere()
     * @see whereToken()
     * @param string|mixed $identifier Column or expression.
     * @param mixed        $variousA   Operator or value.
     * @param mixed        $variousB   Value is operator specified.
     * @param mixed        $variousC   Specified only in between statements.
     * @return $this
     * @throws DatabaseException
     */
    public function having($identifier, $variousA = null, $variousB = null, $variousC = null)
    {
        $this->whereToken('AND', func_get_args(), $this->havingTokens, $this->havingWrapper());

        return $this;
    }

    /**
     * @see parseWhere()
     * @see whereToken()
     * @param string|mixed $identifier Column or expression.
     * @param mixed        $variousA   Operator or value.
     * @param mixed        $variousB   Value is operator specified.
     * @param mixed        $variousC   Specified only in between statements.
     * @return $this
     * @throws DatabaseException
     */
    public function andHaving($identifier, $variousA = null, $variousB = null, $variousC = null)
    {
        $this->whereToken('AND', func_get_args(), $this->havingTokens, $this->havingWrapper());

        return $this;
    }

    /**
     * @see WhereTrait
     * @see parseWhere()
     * @see whereToken()
     * @param string|mixed $identifier Column or expression.
     * @param mixed        $variousA   Operator or value.
     * @param mixed        $variousB   Value is operator specified.
     * @param mixed        $variousC   Specified only in between statements.
     * @return $this
     * @throws DatabaseException
     */
    public function orHaving($identifier, $variousA = [], $variousB = null, $variousC = null)
    {
        $this->whereToken('OR', func_get_args(), $this->havingTokens, $this->havingWrapper());

        return $this;
    }

    /**
     * Parameter wrapper used to convert all found parameters to valid sql expressions. Used in having
     * on functions.
     *
     * @return \Closure
     */
    private function havingWrapper()
    {
        return function ($parameter)
        {
            if (!$parameter instanceof ParameterInterface && is_array($parameter))
            {
                $parameter = new Parameter($parameter);
            }

            if
            (
                $parameter instanceof SqlFragmentInterface
                && !$parameter instanceof ParameterInterface
                && !$parameter instanceof QueryBuilder
            )
            {
                return $parameter;
            }

            $this->havingParameters[] = $parameter;

            return $parameter;
        };
    }
}