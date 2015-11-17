<?php
/**
 * Spiral Framework.
 *
 * @license MIT
 * @author  Anton Titov (Wolfy-J)
 */
namespace Spiral\ODM\Configs;

use Spiral\Core\ArrayConfig;

/**
 * Translation component configuration.
 */
class ODMConfig extends ArrayConfig
{
    /**
     * Configuration section.
     */
    const CONFIG = 'odm';

    /**
     * @var array
     */
    protected $config = [
        'default'   => '',
        'aliases'   => [],
        'databases' => [],
        'schemas'   => [
            'mutators'       => [],
            'mutatorAliases' => []
        ]
    ];
}