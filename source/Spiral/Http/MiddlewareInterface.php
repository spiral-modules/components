<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Common interface for spiral middlewares.
 */
interface MiddlewareInterface
{
    /**
     * Pass request thought middleware and receive resulted response.
     *
     * @param ServerRequestInterface $request
     * @param \Closure               $next Next middleware/target. Always returns ResponseInterface.
     * @return ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request, \Closure $next);
}