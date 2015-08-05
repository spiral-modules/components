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
use Spiral\Core\Singleton;
use Spiral\Core\Traits\ConfigurableTrait;

/**
 * Used to configure instances of Validator with set of aliases, checkers and etc.
 */
class ValidationProvider extends Singleton
{
    /**
     * Provides configuration.
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
     * @param ConfiguratorInterface $configurator
     */
    public function __construct(ConfiguratorInterface $configurator)
    {
        $this->config = $configurator->getConfig(static::CONFIG);
    }
}
