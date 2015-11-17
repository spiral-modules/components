<?php
/**
 * Spiral Framework.
 *
 * @license MIT
 * @author  Anton Titov (Wolfy-J)
 */
namespace Spiral\Http\Configs;

use Spiral\Core\ArrayConfig;
use Spiral\Http\Routing\Router;

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
        'basePath'     => '/',
        'isolate'      => true,
        'exposeErrors' => true,
        'cookies'      => [
            'domain' => '.%s',
            'method' => 'encrypt',
        ],
        'headers'      => [],
        'endpoint'     => null,
        'middlewares'  => [],
        'router'       => [
            'class' => Router::class,
//            'default' => [
//                'pattern'     => '[<controller>[/<action>[/<id>]]]',
//                'namespace'   => 'Controllers',
//                'postfix'     => 'Controller',
//                'defaults'    => [
//                    'controller' => 'home'
//                ],
//                'controllers' => [
//                    'index' => Controllers\HomeController::class
//                ]
//            ]
        ],
        'httpErrors'   => []
    ];
}