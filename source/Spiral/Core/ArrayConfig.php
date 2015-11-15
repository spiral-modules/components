<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Core;

use Spiral\Core\Exceptions\ConfigException;

/**
 * Generic implementation of array based configuration.
 */
class ArrayConfig extends Component implements ConfigInterface, \ArrayAccess, \IteratorAggregate
{
    /**
     * Spiral provides ability to automatically inject configs using configurator.
     */
    const INJECTOR = ConfiguratorInterface::class;

    /**
     * Configuration data.
     *
     * @var array
     */
    protected $config = [];

    /**
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        if (!empty($config)) {
            $this->config = $config;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function toArray()
    {
        return $this->config;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->config);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($offset)
    {
        if (!$this->offsetExists($offset)) {
            throw new ConfigException("Undefined configuration key '{$offset}'.");
        }

        return $this->config[$offset];
    }

    /**
     *{@inheritdoc}
     *
     * @throws ConfigException
     */
    public function offsetSet($offset, $value)
    {
        throw new ConfigException(
            "Unable to change configuration data, configs are treated as immutable."
        );
    }

    /**
     *{@inheritdoc}
     *
     * @throws ConfigException
     */
    public function offsetUnset($offset)
    {
        throw new ConfigException(
            "Unable to change configuration data, configs are treated as immutable."
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->config);
    }
}