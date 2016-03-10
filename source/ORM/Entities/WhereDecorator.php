<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\ORM\Entities;

use Spiral\Database\Builders\Prototypes\AbstractSelect;
use Spiral\Database\Exceptions\BuilderException;

/**
 * WhereDecorator used to trick user functions and route where() calls to specified destination
 * (where, onWhere, etc). This functionality used to describe WHERE conditions in ORM loaders using
 * unified where syntax.
 *
 * Decorator can additionally decorate target table name, using magic expression "{@}". Table name
 * decoration is required as Loader target table can be unknown for user.
 */
class WhereDecorator
{
    /**
     * Target function postfix. All requests will be routed using this pattern and "or", "and"
     * prefixes.
     *
     * @var string
     */
    protected $target = 'where';

    /**
     * Decorator will replace {@} with this alias in every where column.
     *
     * @var string
     */
    protected $alias = '';

    /**
     * Decorated query builder.
     *
     * @var AbstractSelect
     */
    protected $query = null;

    /**
     * @param AbstractSelect $query
     * @param string         $target
     * @param string         $alias
     */
    public function __construct(AbstractSelect $query, $target = 'where', $alias = '')
    {
        $this->query = $query;
        $this->target = $target;
        $this->alias = $alias;
    }

    /**
     * Update target method all where requests should be router into.
     *
     * @param string $target
     */
    public function setTarget($target)
    {
        $this->target = $target;
    }

    /**
     * Get active routing target.
     *
     * @return string
     */
    public function getTarget()
    {
        return $this->target;
    }

    /**
     * Simple WHERE condition with various set of arguments. Routed to where/on/having based
     * on decorator settings.
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
    public function where($identifier, $variousA = null, $variousB = null, $variousC = null)
    {
        if ($identifier instanceof \Closure) {
            call_user_func($identifier, $this);

            return $this;
        }

        //We have to prepare only first argument
        $arguments = func_get_args();
        $arguments[0] = $this->prepare($arguments[0]);

        //Routing where
        call_user_func_array([$this->query, $this->target], $arguments);

        return $this;
    }

    /**
     * Simple AND WHERE condition with various set of arguments. Routed to where/on/having based
     * on decorator settings.
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
    public function andWhere($identifier, $variousA = null, $variousB = null, $variousC = null)
    {
        if ($identifier instanceof \Closure) {
            call_user_func($identifier, $this);

            return $this;
        }

        //We have to prepare only first argument
        $arguments = func_get_args();
        $arguments[0] = $this->prepare($arguments[0]);

        //Routing where
        call_user_func_array([$this->query, 'and' . ucfirst($this->target)], $arguments);

        return $this;
    }

    /**
     * Simple OR WHERE condition with various set of arguments. Routed to where/on/having based
     * on decorator settings.
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
    public function orWhere($identifier, $variousA = [], $variousB = null, $variousC = null)
    {
        if ($identifier instanceof \Closure) {
            call_user_func($identifier, $this);

            return $this;
        }

        //We have to prepare only first argument
        $arguments = func_get_args();
        $arguments[0] = $this->prepare($arguments[0]);

        //Routing where
        call_user_func_array([$this->query, 'or' . ucfirst($this->target)], $arguments);

        return $this;
    }

    /**
     * Helper function used to replace {@} alias with actual table name.
     *
     * @param mixed $where
     *
     * @return mixed
     */
    protected function prepare($where)
    {
        if (is_string($where)) {
            return str_replace('{@}', $this->alias, $where);
        }

        if (!is_array($where)) {
            return $where;
        }

        $result = [];
        foreach ($where as $column => $value) {
            if (is_string($column) && !is_int($column)) {
                $column = str_replace('{@}', $this->alias, $column);
            }

            $result[$column] = !is_array($value) ? $value : $this->prepare($value);
        }

        return $result;
    }
}
