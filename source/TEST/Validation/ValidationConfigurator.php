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
use Spiral\Core\ContainerInterface;
use Spiral\Core\Singleton;
use Spiral\Core\Traits\ConfigurableTrait;

class ValidationConfigurator extends Singleton
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
     * ValidationManager is responsible for creating validators.
     *
     * @param ConfiguratorInterface $configurator
     */
    public function __construct(ConfiguratorInterface $configurator)
    {
        $this->config = $configurator->getConfig(static::CONFIG);
    }
}
