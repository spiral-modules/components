<?php
/**
 * Spiral Framework.
 *
 * @license MIT
 * @author  Anton Titov (Wolfy-J)
 */
namespace Spiral\Http\Configs;

use Spiral\Core\ArrayConfig;

/**
 * HttpDispatcher configuration.
 */
class HttpConfig extends ArrayConfig
{
    /**
     * HttpConfig can be used by multiple classes including cookie middlewares, this should speed
     * up it's loading a little bit.
     */
    const SINGLETON = self::class;

    /**
     * Configuration section.
     */
    const CONFIG = 'http';

    /**
     * @var array
     */
    protected $config = [
    ];
}