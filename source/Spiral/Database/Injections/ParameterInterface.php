<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Database\Injections;

/**
 * Parameter interface is very similar to sql fragments, however it may not only mock sql
 * expressions but also data-set of parameters to be injected into this expression.
 *
 * Usually used for complex set of parameters or late parameter binding.
 *
 * Database implementation must inject parameter SQL into expression, but use parameter value to be
 * sent to database.
 */
interface ParameterInterface extends ExpressionInterface
{
    /**
     * Get mocked parameter value or values in array form.
     *
     * @return mixed|array
     */
    public function getValue();

    /**
     * Change parameter value.
     *
     * @param mixed $value
     */
    public function setValue($value);

    /**
     * Parameter type.
     *
     * @return int|mixed
     */
    public function getType();

    /**
     * In cases when parameter mock arrays such method has to return all nested values on one level,
     * when parameter mock singular value - it has to return array of itself.
     *
     * @return ParameterInterface[]
     */
    public function flatten();
}