<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Http\Middlewares;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Spiral\Http\HttpDispatcher;
use Spiral\Http\MiddlewareInterface;

/**
 * Specify headers specified in HttpDispatcher config.
 */
class DispatcherHeaders implements MiddlewareInterface
{
    /**
     * @var array
     */
    protected $headers = [];

    /**
     * @param HttpDispatcher $http
     */
    public function __construct(HttpDispatcher $http)
    {
        $this->headers = $http->config()['headers'];
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(ServerRequestInterface $request, \Closure $next = null)
    {
        /**
         * @var ResponseInterface $response
         */
        $response = $next($request);
        foreach ($this->headers as $header => $value)
        {
            if (!$response->hasHeader($header))
            {
                $response = $response->withHeader($header, $value);
            }
        }

        return $response;
    }
}