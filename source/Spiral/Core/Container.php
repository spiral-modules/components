<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Core;

use Spiral\Core\Container\SaturableInterlace;
use Spiral\Core\Exceptions\Container\ArgumentException;
use Spiral\Core\Exceptions\Container\InstanceException;
use ReflectionFunctionAbstract as ContextFunction;

/**
 * Default implementation of IoC container, support controllable injections and post controller
 * dependencies.
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
     * First constructed container will become global container by default.
     */
    public function __construct()
    {
        if (empty(self::container()))
        {
            self::setContainer($this);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get($alias, $parameters = [], \ReflectionParameter $context = null)
    {
        if ($alias == ContainerInterface::class)
        {
            //Shortcut
            return $this;
        }

        if (!isset($this->bindings[$alias]))
        {
            return $this->createInstance($alias, $parameters);
        }

        if (is_object($binding = $this->bindings[$alias]))
        {
            //Singleton
            return $binding;
        }

        if (is_string($binding))
        {
            //Binding is pointing to something else
            $instance = $this->get($binding, $parameters, $context);

            if ($instance instanceof SingletonInterface)
            {
                //To prevent double binding
                $this->bindings[$binding] = $this->bindings[get_class($instance)] = $instance;
            }

            return $instance;
        }

        if (is_array($binding))
        {
            if (is_string($binding[0]))
            {
                //Class name with singleton flag
                $instance = $this->get($binding[0], $parameters, $context);
            }
            else
            {
                //Closure with singleton flag
                $instance = call_user_func_array($binding[0], $parameters);
            }

            if ($binding[1])
            {
                //Singleton
                $this->bindings[$alias] = $instance;
            }

            return $instance;
        }

        return null;
    }

    /**
     * Create instance of desired class.
     *
     * @param string $class
     * @param array  $parameters Constructor parameters.
     * @return object
     * @throws InstanceException
     */
    private function createInstance($class, array $parameters)
    {
        $reflector = new \ReflectionClass($class);

        if (!empty($context) && $injector = $reflector->getConstant('INJECTOR'))
        {
            /**
             * We have to construct class here. Remember about this magick constant?
             */
            return call_user_func(
                [$this->get($injector), 'createInjection'],
                $reflector, $context, $this
            );
        }

        if (!$reflector->isInstantiable())
        {
            throw new InstanceException("Class '{$class}' can not be constructed.");
        }

        if (!empty($constructor = $reflector->getConstructor()))
        {
            $instance = $reflector->newInstanceArgs(
                $this->resolveArguments($constructor, $parameters)
            );
        }
        else
        {
            //No constructor specified
            $instance = $reflector->newInstance();
        }

        if (!empty($singleton = $reflector->getConstant('SINGLETON')))
        {
            //Component declared SINGLETON constant, binding as constant value and class name.
            $this->bindings[$reflector->getName()] = $this->bindings[$singleton] = $instance;
        }

        if ($instance instanceof SaturableInterlace)
        {
            //Saturating object with required dependencies
            $depends = $reflector->getMethod(SaturableInterlace::DEPENDENT_METHOD);
            $depends->invoke($instance, $this->resolveArguments($depends, $parameters));
        }

        return $instance;
    }

    /**
     * {@inheritdoc}
     */
    public function resolveArguments(ContextFunction $reflection, array $parameters = [])
    {
        $arguments = [];
        foreach ($reflection->getParameters() as $parameter)
        {
            $name = $parameter->getName();

            if (empty($class = $parameter->getClass()))
            {
                if (array_key_exists($name, $parameters))
                {
                    //Scalar value supplied by user
                    $arguments[] = $parameters[$name];
                    continue;
                }

                if ($parameter->isDefaultValueAvailable())
                {
                    //Or default value?
                    $arguments[] = $parameter->getDefaultValue();
                    continue;
                }

                //Unable to resolve scalar argument value
                throw new ArgumentException($parameter, $reflection);
            }

            if (isset($parameters[$name]) && is_object($parameters[$name]))
            {
                //Supplied by user
                $arguments[] = $parameters[$name];
                continue;
            }

            try
            {
                //Trying to resolve dependency
                $arguments[] = $this->get($class->getName(), [], $parameter);

                continue;
            }
            catch (InstanceException $exception)
            {
                if ($parameter->isDefaultValueAvailable())
                {
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
     */
    public function bind($alias, $resolver)
    {
        if (is_array($resolver) || $resolver instanceof \Closure)
        {
            $this->bindings[$alias] = [$resolver, false];

            return;
        }

        $this->bindings[$alias] = $resolver;
    }

    /**
     * {@inheritdoc}
     */
    public function bindSingleton($alias, $resolver)
    {
        $this->bindings[$alias] = [$resolver, true];
    }

    /**
     * {@inheritdoc}
     */
    public function replace($alias, $resolver)
    {
        $payload = [$alias, null];
        if (isset($this->bindings[$alias]))
        {
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
        if (!empty($resolver))
        {
            //Restoring original value
            $this->bindings[$alias] = $replacePayload;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function hasBinding($alias)
    {
        return isset($this->bindings[$alias]);
    }

    /**
     * {@inheritdoc}
     */
    public function hasInstance($alias)
    {
        if (!$this->hasBinding($alias))
        {
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
}