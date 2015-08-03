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
use Spiral\Database\Injections\SQLFragmentInterface;
use Spiral\Database\ParameterInterface;
use Spiral\Database\QueryBuilder;

/**
 * Set of functions to simplify generation of HAVING query statement. Uses functionality defined
 * in AbstractWhere prototype.
 *
 * @see AbstractWhere
 */
trait HavingTrait
{
    /**
     * Set of generated having tokens, format must be supported by QueryCompilers.
     *
     * @see AbstractWhere
     * @var array
     */
    protected $havingTokens = [];

    /**
     * Parameters collected while generating having tokens, must be in a same order as parameters
     * in resulted query.
     *
     * @var array
     */
    protected $havingParameters = [];

    /**
     * Simple HAVING condition with various set of arguments.
     *
     * @see AbstractWhere
     * @param string|mixed $identifier Column or expression.
     * @param mixed        $variousA   Operator or value.
     * @param mixed        $variousB   Value, if operator specified.
     * @param mixed        $variousC   Required only in between statements.
     * @return $this
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
     * @param string|mixed $identifier Column or expression.
     * @param mixed        $variousA   Operator or value.
     * @param mixed        $variousB   Value, if operator specified.
     * @param mixed        $variousC   Required only in between statements.
     * @return $this
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
     * @param string|mixed $identifier Column or expression.
     * @param mixed        $variousA   Operator or value.
     * @param mixed        $variousB   Value, if operator specified.
     * @param mixed        $variousC   Required only in between statements.
     * @return $this
     * @throws BuilderException
     */
    public function orHaving($identifier, $variousA = [], $variousB = null, $variousC = null)
    {
        $this->whereToken('OR', func_get_args(), $this->havingTokens, $this->havingWrapper());

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
     * Applied to every potential parameter while having tokens generation.
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
                $parameter instanceof SQLFragmentInterface
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