<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Core;

use Interop\Container\ContainerInterface;
use ReflectionFunctionAbstract as ContextFunction;
use Spiral\Core\Container\InjectableInterface;
use Spiral\Core\Container\InjectorInterface;
use Spiral\Core\Container\SingletonInterface;
use Spiral\Core\Exceptions\Container\ArgumentException;
use Spiral\Core\Exceptions\Container\AutowireException;
use Spiral\Core\Exceptions\Container\ContainerException;
use Spiral\Core\Exceptions\Container\InjectionException;
use Spiral\Core\Exceptions\Container\NotFoundException;

/**
 * 500 lines of code size auto-wiring container with declarative singletons, contextual injections,
 * bindings, lazy factories and Container Interop compatible. :)
 *
 * Container does not support setter injections, private properties and etc. Normally it will work
 * with classes only to be as much invisible as possible.
 *
 * @see \Spiral\Core\Container::registerInstance() to add your own behaviours.
 *
 * @see InjectableInterface
 * @see SingletonInterface
 */
class Container extends Component implements ContainerInterface, FactoryInterface, ResolverInterface
{
    /**
     * IoC bindings.
     *
     * @what-if private
     * @invisible
     *
     * @var array
     */
    protected $bindings = [
        ContainerInterface::class => self::class,
        FactoryInterface::class   => self::class,
        ResolverInterface::class  => self::class
    ];

    /**
     * Registered injectors.
     *
     * @what-if private
     * @invisible
     *
     * @var array
     */
    protected $injectors = [];

    /**
     * Container constructor.
     */
    public function __construct()
    {
        $this->bindings[static::class] = self::class;
        $this->bindings[self::class] = $this;
    }

    /**
     * {@inheritdoc}
     */
    public function has($alias)
    {
        return array_key_exists($alias, $this->bindings);
    }

    /**
     * {@inheritdoc}
     *
     * Context parameter will be passed to class injectors, which makes possible to use this method
     * as:
     * $this->container->get(DatabaseInterface::class, 'default');
     *
     * @param string|null $context Call context.
     */
    public function get($alias, $context = null)
    {
        //Direct bypass to construct, i might think about this option... or not.
        return $this->make($alias, [], $context);
    }

    /**
     * {@inheritdoc}
     *
     * @param string|null $context Related to parameter caused injection if any.
     */
    final public function make(string $class, $parameters = [], string $context = null)
    {
        if (!isset($this->bindings[$class])) {
            //No direct instructions how to construct class, make is automatically
            return $this->autowire($class, $parameters, $context);
        }

        if (is_object($binding = $this->bindings[$class])) {
            //When binding is instance, assuming singleton
            return $binding;
        }

        if (is_string($binding)) {
            //Binding is pointing to something else
            return $this->make($binding, $parameters, $context);
        }

        if (is_string($binding[0])) {
            //Class name
            $instance = $this->make($binding[0], $parameters, $context);
        } elseif ($binding[0] instanceof \Closure) {
            $reflection = new \ReflectionFunction($binding[0]);

            //Invoking Closure with resolved arguments
            $instance = $reflection->invokeArgs(
                $this->resolveArguments($reflection, $parameters, $context)
            );
        } elseif (is_array($binding[0]) && isset($binding[0][1])) {
            //In a form of resolver and method
            list($resolver, $method) = $binding[0];

            //Resolver instance (i.e. [ClassName::class, 'method'])
            $resolver = $this->get($resolver);
            $method = new \ReflectionMethod($resolver, $method);
            $method->setAccessible(true);

            //Invoking factory method with resolved arguments
            $instance = $method->invokeArgs(
                $resolver,
                $this->resolveArguments($method, $parameters, $context)
            );
        } else {
            //No idea what was this binding was
            throw new ContainerException("Invalid binding for '{$class}'");
        }

        if ($binding[1]) {
            //Declared singleton
            $this->bindings[$class] = $instance;
        }

        if (!is_object($instance)) {
            //Non object bindings are allowed
            return $instance;
        }

        return $instance;
    }

    /**
     * {@inheritdoc}
     *
     * @param string $context
     */
    final public function resolveArguments(
        ContextFunction $reflection,
        array $parameters = [],
        string $context = null
    ): array {
        $arguments = [];
        foreach ($reflection->getParameters() as $parameter) {
            try {
                //Information we need to know about argument in order to resolve it's value
                $name = $parameter->getName();
                $class = $parameter->getClass();
            } catch (\Throwable $e) {
                //Possibly invalid class definition or syntax error
                throw new ContainerException($e->getMessage(), $e->getCode(), $e);
            }

            //No declared type or scalar type or array
            if (empty($class)) {
                //Provided from outside
                if (array_key_exists($name, $parameters)) {
                    //Make sure it's properly typed
                    $this->assertType($parameter, $reflection, $parameters[$name]);
                    $arguments[] = $parameters[$name];

                    continue;
                }

                if ($parameter->isDefaultValueAvailable()) {
                    //Default value
                    $arguments[] = $parameter->getDefaultValue();
                    continue;
                }

                //Unable to resolve scalar argument value
                throw new ArgumentException($parameter, $reflection);
            }

            if (isset($parameters[$name]) && is_object($parameters[$name])) {
                //Supplied by user but only as object!
                $arguments[] = $parameters[$name];
                continue;
            }

            try {
                //Trying to resolve dependency (contextually)
                $arguments[] = $this->get($class->getName(), $name);

                continue;
            } catch (AutowireException $e) {
                if ($parameter->isOptional()) {
                    //This is optional dependency, skip
                    $arguments[] = null;
                    continue;
                }

                throw $e;
            }
        }

        return $arguments;
    }

    /**
     * Bind value resolver to container alias. Resolver can be class name (will be constructed
     * for each method call), function array or Closure (executed every call). Only object resolvers
     * supported by this method.
     *
     * @param string                $alias
     * @param string|array|callable $resolver
     *
     * @return self
     */
    final public function bind(string $alias, $resolver): Container
    {
        if (is_array($resolver) || $resolver instanceof \Closure) {
            //Array means = execute me, false = not singleton
            $this->bindings[$alias] = [$resolver, false];

            return $this;
        }

        $this->bindings[$alias] = $resolver;

        return $this;
    }

    /**
     * Bind value resolver to container alias to be executed as cached. Resolver can be class name
     * (will be constructed only once), function array or Closure (executed only once call).
     *
     * @param string                $alias
     * @param string|array|callable $resolver
     *
     * @return self
     */
    final public function bindSingleton(string $alias, $resolver): Container
    {
        if (is_object($resolver) && !$resolver instanceof \Closure) {
            //Direct binding to an instance
            $this->bindings[$alias] = $resolver;

            return $this;
        }

        $this->bindings[$alias] = [$resolver, true];

        return $this;
    }

    /**
     * Specify binding which has to be used for class injection.
     *
     * @param string        $class
     * @param string|object $injector
     *
     * @return self
     */
    public function bindInjector(string $class, $injector): Container
    {
        if (!is_string($injector)) {
            throw new \InvalidArgumentException('Injector can only be set as string binding');
        }

        $this->injectors[$class] = $injector;

        return $this;
    }

    /**
     * Check if given class has associated injector.
     *
     * @param \ReflectionClass $reflection
     *
     * @return bool
     */
    public function hasInjector(\ReflectionClass $reflection): bool
    {
        if (isset($this->injectors[$reflection->getName()])) {
            return true;
        }

        //Auto injection!
        return $reflection->isSubclassOf(InjectableInterface::class);
    }

    /**
     * Check if alias points to constructed instance (singleton).
     *
     * @param string $alias
     *
     * @return bool
     */
    final public function hasInstance(string $alias): bool
    {
        if (!$this->has($alias)) {
            return false;
        }

        while (isset($this->bindings[$alias]) && is_string($this->bindings[$alias])) {
            //Checking alias tree
            $alias = $this->bindings[$alias];
        }

        return isset($this->bindings[$alias]) && is_object($this->bindings[$alias]);
    }

    /**
     * @param string $alias
     */
    final public function removeBinding(string $alias)
    {
        unset($this->bindings[$alias]);
    }

    /**
     * @param string $class
     */
    final public function removeInjector(string $class)
    {
        unset($this->injectors[$class]);
    }

    /**
     * Every declared Container binding. Must not be used in production code due container format is
     * vary.
     *
     * @return array
     */
    final public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Every binded injector.
     *
     * @return array
     */
    final public function getInjectors(): array
    {
        return $this->injectors;
    }

    /**
     * Automatically create class.
     *
     * @param string $class
     * @param array  $parameters
     * @param string $context
     *
     * @return object
     *
     * @throws AutowireException
     */
    final protected function autowire(string $class, array $parameters, string $context = null)
    {
        try {
            if (!class_exists($class)) {
                throw new NotFoundException("Undefined class or binding '{$class}'");
            }
        } catch (\Error $e) {
            //Issues with syntax or class definition
            throw new ContainerException($e->getMessage(), $e->getCode(), $e);
        }

        //Automatically create instance
        $instance = $this->createInstance($class, $parameters, $context);

        //Apply registration functions to created instance
        return $this->registerInstance($instance, $parameters);
    }

    /**
     * Get injector associated with given class.
     *
     * @param \ReflectionClass $reflection
     *
     * @return InjectorInterface
     */
    protected function getInjector(\ReflectionClass $reflection): InjectorInterface
    {
        if (isset($this->injectors[$reflection->getName()])) {
            //Stated directly
            $injector = $this->get($this->injectors[$reflection->getName()]);
        } else {
            //Auto-injection!
            $injector = $this->get($reflection->getConstant('INJECTOR'));
        }

        if (!$injector instanceof InjectorInterface) {
            throw new InjectionException(
                "Class '" . get_class($injector) . "' must be an instance of InjectorInterface for '{$reflection->getName()}'"
            );
        }

        return $injector;
    }

    /**
     * Register instance in container, might perform methods like auto-singletons, log populations
     * and etc. Can be extended.
     *
     * @param object $instance
     * @param array  $parameters
     *
     * @return object
     */
    protected function registerInstance($instance, array $parameters)
    {
        //Declarative singletons
        if (empty($parameters) && $instance instanceof SingletonInterface) {
            $singleton = get_class($instance);

            if (!isset($this->bindings[$singleton])) {
                $this->bindings[$singleton] = $instance;
            }
        }

        //Your code can go here

        return $instance;
    }

    /**
     * Create instance of desired class.
     *
     * @param string      $class
     * @param array       $parameters Constructor parameters.
     * @param string|null $context
     *
     * @return object
     *
     * @throws ContainerException
     */
    private function createInstance(string $class, array $parameters, string $context = null)
    {
        $reflection = new \ReflectionClass($class);

        //We have to construct class using external injector
        if (empty($parameters) && $this->hasInjector($reflection)) {
            //Creating class using injector/factory
            $instance = $this->getInjector($reflection)->createInjection(
                $reflection,
                $context
            );

            if (!$reflection->isInstance($instance)) {
                throw new InjectionException("Invalid injection response for '{$reflection->getName()}'");
            }

            return $instance;
        }

        if (!$reflection->isInstantiable()) {
            throw new ContainerException("Class '{$class}' can not be constructed");
        }

        if (!empty($constructor = $reflection->getConstructor())) {
            //Using constructor with resolved arguments
            $instance = $reflection->newInstanceArgs(
                $this->resolveArguments($constructor, $parameters)
            );
        } else {
            //No constructor specified
            $instance = $reflection->newInstance();
        }

        return $instance;
    }

    /**
     * Assert that given value are matched parameter type.
     *
     * @param \ReflectionParameter        $parameter
     * @param \ReflectionFunctionAbstract $context
     * @param mixed                       $value
     *
     * @throws ArgumentException
     */
    private function assertType(
        \ReflectionParameter $parameter,
        \ReflectionFunctionAbstract $context,
        $value
    ) {
        if (is_null($value)) {
            if (!$parameter->isOptional()) {
                throw new ArgumentException($parameter, $context);
            }

            return;
        }

        $type = $parameter->getType();

        if ($type == 'array' && !is_array($value)) {
            throw new ArgumentException($parameter, $context);
        }

        if (($type == 'int' || $type == 'float') && !is_numeric($value)) {
            throw new ArgumentException($parameter, $context);
        }

        if ($type == 'bool' && !is_bool($value) && !is_numeric($value)) {
            throw new ArgumentException($parameter, $context);
        }
    }
}