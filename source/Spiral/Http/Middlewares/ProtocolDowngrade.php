<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Http\Middlewares;

use Psr\Http\Message\ServerRequestInterface;
use Spiral\Http\MiddlewareInterface;

/**
 * Downgrades response protocol to 1.0 version.
 */
class ProtocolDowngrade implements MiddlewareInterface
{
    /**
     * {@inheritdoc}
     */
    public function __invoke(ServerRequestInterface $request, \Closure $next = null)
    {
        return $next($request)->withProtocolVersion('1.0');
    }
}