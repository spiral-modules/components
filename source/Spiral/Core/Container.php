<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Core;

use ReflectionFunctionAbstract as ContextFunction;
use Spiral\Core\Container\SingletonInterface;
use Spiral\Core\Exceptions\Container\ArgumentException;
use Spiral\Core\Exceptions\Container\ContainerException;
use Spiral\Core\Exceptions\Container\InstanceException;

/**
 * Default implementation of IoC container, support controllable injections and post controller
 * dependencies.
 *
 * There is no way to bind values at this moment.
 */
class Container extends Component implements ContainerInterface
{
    /**
     * IoC bindings. Binding one class or interface to another class and interface. :)
     *
     * @invisible
     * @var array
     */
    protected $bindings = [];

    /**
     * {@inheritdoc}
     */
    public function has($alias)
    {
        return isset($this->bindings[$alias]);
    }

    /**
     * {@inheritdoc}
     */
    public function get($alias)
    {
        //Direct bypass to construct, i might think about this option... or not.
        return $this->construct($alias);
    }

    /**
     * {@inheritdoc}
     *
     * @param \ReflectionParameter $context Related to parameter caused injection if any.
     */
    public function construct($class, $parameters = [], \ReflectionParameter $context = null)
    {
        if ($class == ContainerInterface::class) {
            //Shortcut
            return $this;
        }

        if (!isset($this->bindings[$class])) {
            //OK, we can create class by ourselves
            $instance = $this->createInstance($class, $parameters, $context, $reflector);

            /**
             * @var \ReflectionClass $reflector
             */
            if (
                $instance instanceof SingletonInterface
                && !empty($singleton = $reflector->getConstant('SINGLETON'))
            ) {
                //Component declared SINGLETON constant, binding as constant value and class name.
                $this->bindings[$singleton] = $instance;
            }

            return $instance;
        }

        if (is_object($binding = $this->bindings[$class])) {
            //Singleton
            return $binding;
        }

        if (is_string($binding)) {
            //Binding is pointing to something else
            return $this->construct($binding, $parameters, $context);
        }

        if (is_array($binding)) {
            if (is_string($binding[0])) {
                //Class name with singleton flag
                $instance = $this->construct($binding[0], $parameters, $context);
            } else {
                //Closure with singleton flag
                $instance = call_user_func($binding[0], $this);
            }

            if ($binding[1]) {
                //Singleton
                $this->bindings[$class] = $instance;
            }

            return $instance;
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function resolveArguments(ContextFunction $reflection, array $parameters = [])
    {
        $arguments = [];
        foreach ($reflection->getParameters() as $parameter) {
            $name = $parameter->getName();

            try {
                $class = $parameter->getClass();
            } catch (\ReflectionException $exception) {
                throw new ContainerException(
                    $exception->getMessage(),
                    $exception->getCode(),
                    $exception
                );
            }

            if (empty($class)) {
                if (array_key_exists($name, $parameters)) {
                    //Scalar value supplied by user
                    $arguments[] = $parameters[$name];
                    continue;
                }

                if ($parameter->isDefaultValueAvailable()) {
                    //Or default value?
                    $arguments[] = $parameter->getDefaultValue();
                    continue;
                }

                //Unable to resolve scalar argument value
                throw new ArgumentException($parameter, $reflection);
            }

            if (isset($parameters[$name]) && is_object($parameters[$name])) {
                //Supplied by user
                $arguments[] = $parameters[$name];
                continue;
            }

            try {
                //Trying to resolve dependency
                $arguments[] = $this->construct($class->getName(), [], $parameter);

                continue;
            } catch (InstanceException $exception) {
                if ($parameter->isDefaultValueAvailable()) {
                    //Let's try to use default value instead
                    $arguments[] = $parameter->getDefaultValue();
                    continue;
                }

                throw $exception;
            }
        }

        return $arguments;
    }

    /**
     * {@inheritdoc}
     *
     * @todo: add Parameters?
     */
    public function bind($alias, $resolver)
    {
        if (is_array($resolver) || $resolver instanceof \Closure) {
            $this->bindings[$alias] = [$resolver, false];

            return;
        }

        $this->bindings[$alias] = $resolver;
    }

    /**
     * {@inheritdoc}
     *
     * @todo: add Parameters?
     */
    public function bindSingleton($alias, $resolver)
    {
        if (is_object($resolver) && !$resolver instanceof \Closure) {
            $this->bindings[$alias] = $resolver;

            return;
        }

        $this->bindings[$alias] = [$resolver, true];
    }

    /**
     * {@inheritdoc}
     *
     * @todo: add Parameters?
     */
    public function replace($alias, $resolver)
    {
        $payload = [$alias, null];
        if (isset($this->bindings[$alias])) {
            $payload[1] = $this->bindings[$alias];
        }

        $this->bind($alias, $resolver);

        return $payload;
    }

    /**
     * {@inheritdoc}
     */
    public function restore($replacePayload)
    {
        list($alias, $resolver) = $replacePayload;

        unset($this->bindings[$alias]);

        if (!empty($resolver)) {
            //Restoring original value
            $this->bindings[$alias] = $replacePayload;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function hasInstance($alias)
    {
        if (!$this->has($alias)) {
            return false;
        }

        return is_object($this->bindings[$alias]);
    }

    /**
     * {@inheritdoc}
     */
    public function removeBinding($alias)
    {
        unset($this->bindings[$alias]);
    }

    /**
     * Every declared Container binding. Must not be used in production code due container format is
     * vary.
     *
     * @return array
     */
    public function getBindings()
    {
        return $this->bindings;
    }

    /**
     * Alias for get.
     *
     * @param string $alias
     * @return mixed|null|object
     */
    public function __get($alias)
    {
        return $this->get($alias);
    }

    /**
     * Create instance of desired class.
     *
     * @param string               $class
     * @param array                $parameters Constructor parameters.
     * @param \ReflectionParameter $context
     * @param \ReflectionClass     $reflector  Instance of reflection associated with class,
     *                                         reference.
     * @return object
     * @throws InstanceException
     */
    private function createInstance(
        $class,
        array $parameters,
        \ReflectionParameter $context = null,
        \ReflectionClass &$reflector = null
    ) {
        try {
            $reflector = new \ReflectionClass($class);
        } catch (\ReflectionException $exception) {
            throw new InstanceException(
                $exception->getMessage(), $exception->getCode(), $exception
            );
        }

        if (!empty($context) && $injector = $reflector->getConstant('INJECTOR')) {
            //We have to construct class using external injector.
            //Remember about this magick constant?
            return call_user_func(
                [$this->construct($injector), 'createInjection'],
                $reflector, $context, $this
            );
        }

        if (!$reflector->isInstantiable()) {
            throw new InstanceException("Class '{$class}' can not be constructed.");
        }

        if (!empty($constructor = $reflector->getConstructor())) {
            //Using constructor with resolved arguments
            $instance = $reflector->newInstanceArgs(
                $this->resolveArguments($constructor, $parameters)
            );
        } else {
            //No constructor specified
            $instance = $reflector->newInstance();
        }

        return $instance;
    }
}