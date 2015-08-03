<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Interfaces\Builders\Common;

use Spiral\Database\Exceptions\BuilderException;

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
 */
interface WhereBuilderInterface
{
    /**
     * Tokens for nested OR and AND conditions.
     */
    const TOKEN_AND = "@AND";
    const TOKEN_OR  = "@OR";

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
    public function where($identifier, $variousA = null, $variousB = null, $variousC = null);

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
    public function andWhere($identifier, $variousA = null, $variousB = null, $variousC = null);

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
    public function orWhere($identifier, $variousA = [], $variousB = null, $variousC = null);
}