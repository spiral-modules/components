<?php
/**
 * Spiral Framework.
 *
 * @license MIT
 * @author  Anton Titov (Wolfy-J)
 */
namespace Spiral\Encrypter\Configs;

use Spiral\Core\InjectableConfig;

/**
 * Encrypter configuration.
 */
class EncrypterConfig extends InjectableConfig
{
    /**
     * Configuration section.
     */
    const CONFIG = 'encrypter';

    /**
     * @var array
     */
    protected $config = [
        'key'    => ''
    ];

    /**
     * @return string
     */
    public function getKey()
    {
        return $this->config['key'];
    }
}