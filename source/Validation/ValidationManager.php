<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Validation;

use Spiral\Core\ConfiguratorInterface;
use Spiral\Core\Container\InjectorInterface;
use Spiral\Core\ContainerInterface;
use Spiral\Core\Singleton;
use Spiral\Core\Traits\ConfigurableTrait;

class ValidationManager extends Singleton implements InjectorInterface
{
    /**
     * Required traits.
     */
    use ConfigurableTrait;

    /**
     * Declares to IoC that component instance should be treated as singleton.
     */
    const SINGLETON = self::class;

    /**
     * Configuration section.
     */
    const CONFIG = 'validation';

    /**
     * ContainerInterface.
     *
     * @var ContainerInterface
     */
    protected $container = null;

    /**
     * ValidationManager is responsible for creating validators.
     *
     * @param ConfiguratorInterface $configurator
     * @param ContainerInterface    $container
     */
    public function __construct(ConfiguratorInterface $configurator, ContainerInterface $container)
    {
        $this->config = $configurator->getConfig(static::CONFIG);
        $this->container = $container;
    }

    /**
     * Create instance of ValidatorInterface, for performance reasons class will be created directly,
     * without using container get method.
     *
     * @param array $data
     * @param array $validates
     * @param array $options Custom validation options.
     * @return ValidatorInterface
     */
    public function createValidator(array $data, array $validates, array $options = [])
    {
        $class = $this->config['validator'];

        //Pretty simple right?
        return new $class($data, $validates, $options + $this->config, $this->container);
    }

    /**
     * Injector will receive requested class or interface reflection and reflection linked
     * to parameter in constructor or method.
     *
     * This method can return pre-defined instance or create new one based on requested class. Parameter
     * reflection can be used for dynamic class constructing, for example it can define database name
     * or config section to be used to construct requested instance.
     *
     * @param \ReflectionClass     $class
     * @param \ReflectionParameter $parameter
     * @return mixed
     */
    public function createInjection(\ReflectionClass $class, \ReflectionParameter $parameter)
    {
        //We can use default validator
        return $this->createValidator([], []);
    }
}