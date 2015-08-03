<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Interfaces\Injections;

use Spiral\Database\Injections\SQLFragmentInterface;

/**
 * Parameter interface is very similar to sql fragments, however it can not only mock sql expressions
 * but set of parameters to be injected into this expression.
 *
 * Usually used for complex set of parameters or late parameter binding.
 */
interface ParameterInterface extends SQLFragmentInterface
{
    /**
     * Get mocked parameter value or values in array form.
     *
     * @return mixed|array
     */
    public function getValue();
}