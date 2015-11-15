<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Core;

use Spiral\Core\Container\InjectableInterface;

/**
 * Simple Spiral components config interface.
 */
interface ConfigInterface extends InjectableInterface
{
    /**
     * @param array $config
     */
    public function __construct(array $config);

    /**
     * Serialize config into array.
     *
     * @return array
     */
    public function toArray();
}