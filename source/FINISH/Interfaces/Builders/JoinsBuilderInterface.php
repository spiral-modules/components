<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Interfaces\Builder;
use Spiral\Database\Exceptions\BuilderException;

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
interface JoinsBuilderInterface
{
    /**
     * Register new JOIN with specified type with set of on conditions (linking one table to another,
     * no parametric on conditions allowed here).
     *
     * @param string $type  Join type. Allowed values, LEFT, RIGHT, INNER and etc.
     * @param string $table Joined table name (without prefix), may include AS statement.
     * @param mixed  $on    Simplified on definition linking table names (no parameters allowed) or
     *                      closure.
     * @return self
     * @throws BuilderException
     */
    public function join($type, $table, $on = null);

    /**
     * Simple ON condition with various set of arguments. Can only be used to link column values
     * together, no parametric values allowed.
     *
     * @param mixed $joined   Joined column name or expression.
     * @param mixed $operator Foreign column name, if operator specified.
     * @param mixed $outer    Foreign column name.
     * @return self
     * @throws BuilderException
     */
    public function on($joined = null, $operator = null, $outer = null);

    /**
     * Simple AND ON condition with various set of arguments. Can only be used to link column values
     * together, no parametric values allowed.
     *
     * @param mixed $joined   Joined column name or expression.
     * @param mixed $operator Foreign column name, if operator specified.
     * @param mixed $outer    Foreign column name.
     * @return self
     * @throws BuilderException
     */
    public function andOn($joined = null, $operator = null, $outer = null);

    /**
     * Simple OR ON condition with various set of arguments. Can only be used to link column values
     * together, no parametric values allowed.
     *
     * @param mixed $joined   Joined column name or expression.
     * @param mixed $operator Foreign column name, if operator specified.
     * @param mixed $outer    Foreign column name.
     * @return self
     * @throws BuilderException
     */
    public function orOn($joined = null, $operator = null, $outer = null);

    /**
     * Simple ON WHERE condition with various set of arguments. You can use parametric values in
     * such methods.
     *
     * @see AbstractWhere
     * @param string|mixed $joined   Joined column or expression.
     * @param mixed        $variousA Operator or value.
     * @param mixed        $variousB Value, if operator specified.
     * @param mixed        $variousC Required only in between statements.
     * @return self
     * @throws BuilderException
     */
    public function onWhere($joined, $variousA = null, $variousB = null, $variousC = null);

    /**
     * Simple AND ON WHERE condition with various set of arguments. You can use parametric values in
     * such methods.
     *
     * @see AbstractWhere
     * @param string|mixed $joined   Joined column or expression.
     * @param mixed        $variousA Operator or value.
     * @param mixed        $variousB Value, if operator specified.
     * @param mixed        $variousC Required only in between statements.
     * @return self
     * @throws BuilderException
     */
    public function andOnWhere($joined, $variousA = null, $variousB = null, $variousC = null);

    /**
     * Simple OR ON WHERE condition with various set of arguments. You can use parametric values in
     * such methods.
     *
     * @see AbstractWhere
     * @param string|mixed $joined   Joined column or expression.
     * @param mixed        $variousA Operator or value.
     * @param mixed        $variousB Value, if operator specified.
     * @param mixed        $variousC Required only in between statements.
     * @return self
     * @throws BuilderException
     */
    public function orOnWhere($joined, $variousA = null, $variousB = null, $variousC = null);
}