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
 * Populates parsedBody data of request with decoded json content if appropriate request header
 * set.
 */
class JsonParser implements MiddlewareInterface
{
    /**
     * {@inheritdoc}
     */
    public function __invoke(ServerRequestInterface $request, \Closure $next = null)
    {
        if ($request->getHeaderLine('Content-Type') == 'application/json') {
            $jsonBody = $request->getBody()->__toString();

            return $next($request->withParsedBody(json_decode($jsonBody, true)));
        }

        return $next($request);
    }
}