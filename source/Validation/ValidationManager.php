<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Validation;

use Spiral\Core\Component;
use Spiral\Core\ConfiguratorInterface;
use Spiral\Core\ContainerInterface;
use Spiral\Core\Singleton;

class ValidationManager extends Singleton
{
    /**
     * Required traits.
     */
    use Component\ConfigurableTrait;

    /**
     * Declares to IoC that component instance should be treated as singleton.
     */
    const SINGLETON = self::class;

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
        $this->config = $configurator->getConfig($this);
        $this->container = $container;
    }

    /**
     * Create instance of ValidatorInterface, for performance reasons class will be created directly,
     * without using container get method.
     *
     * @param array $data
     * @param array $rules
     * @param array $options Custom validation options.
     * @return ValidatorInterface
     */
    public function createValidator(array $data, array $rules, array $options = [])
    {
        $class = $this->config['validator'];

        //Pretty simple right?
        return new $class($data, $rules, $options + $this->config, $this->container);
    }
}