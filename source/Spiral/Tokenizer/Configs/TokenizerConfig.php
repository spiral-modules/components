<?php
/**
 * Spiral Framework.
 *
 * @license MIT
 * @author  Anton Titov (Wolfy-J)
 */
namespace Spiral\Tokenizer\Configs;

use Spiral\Core\ArrayConfig;

/**
 * Translation component configuration.
 */
class TokenizerConfig extends ArrayConfig
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
        'exclude'     => []
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