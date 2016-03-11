<?php
/**
 * Spiral Framework.
 *
 * @license MIT
 * @author  Anton Titov (Wolfy-J)
 */

namespace Spiral\Tokenizer\Configs;

use Spiral\Core\InjectableConfig;

/**
 * Tokenizer component configuration.
 */
class TokenizerConfig extends InjectableConfig
{
    /**
     * Configuration section.
     */
    const CONFIG = 'tokenizer';

    /**
     * @var array
     */
    protected $config = [
        'directories' => [],
        'exclude'     => [],
    ];

    /**
     * @return array
     */
    public function getDirectories()
    {
        return $this->config['directories'];
    }

    /**
     * @return array
     */
    public function getExcludes()
    {
        return $this->config['exclude'];
    }
}
