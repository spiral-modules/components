<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Core\Container;

use Spiral\Core\ExceptionInterface;

class ArgumentException extends \LogicException implements ExceptionInterface
{
    /**
     * Parameter caused error.
     *
     * @var \ReflectionParameter
     */
    protected $parameter = null;

    /**
     * Context method or constructor or function.
     *
     * @var \ReflectionFunctionAbstract
     */
    protected $context = null;

    /**
     * Unresolved argument exception.
     *
     * @param \ReflectionParameter        $parameter
     * @param \ReflectionFunctionAbstract $context
     */
    public function __construct(\ReflectionParameter $parameter, \ReflectionFunctionAbstract $context)
    {
        $this->parameter = $parameter;
        $this->context = $context;

        $name = $context->getName();
        if ($context instanceof \ReflectionMethod)
        {
            $name = $context->class . '::' . $name;
        }

        parent::__construct("Unable to resolve '{$parameter->getName()}' argument in '{$name}'.");
    }

    /**
     * Get parameter.
     *
     * @return \ReflectionParameter
     */
    public function getParameter()
    {
        return $this->parameter;
    }

    /**
     * Get context.
     *
     * @return \ReflectionFunctionAbstract
     */
    public function getContext()
    {
        return $this->context;
    }
}