<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Encrypter;

use Spiral\Core\ConfiguratorInterface;
use Spiral\Core\Container\InjectorInterface;
use Spiral\Core\Exceptions\Container\ContainerException;

class EncrypterManager implements InjectorInterface
{
    /**
     * Configuration section.
     */
    const CONFIG = 'encrypter';

    /**
     * Component configuration.
     *
     * @var array
     */
    protected $config = [];

    /**
     * @param ConfiguratorInterface $configurator
     */
    public function __construct(ConfiguratorInterface $configurator)
    {
        $this->config = $configurator->getConfig(static::CONFIG) + ['cipher' => null];
    }

    /**
     * Injector will receive requested class or interface reflection and reflection linked
     * to parameter in constructor or method.
     *
     * This method can return pre-defined instance or create new one based on requested class.
     * Parameter reflection can be used for dynamic class constructing, for example it can define
     * database name or config section to be used to construct requested instance.
     *
     * @param \ReflectionClass $class   Request class type.
     * @param string           $context Parameter or alias name.
     * @return object
     * @throws ContainerException
     * @throws \ErrorException
     */
    public function createInjection(\ReflectionClass $class, $context = null)
    {
        return $class->newInstance($this->config['key'], $this->config['cipher']);
    }
}