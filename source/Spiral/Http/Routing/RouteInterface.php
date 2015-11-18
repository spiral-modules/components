<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Http\Routing;

use Cocur\Slugify\SlugifyInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Spiral\Core\Container;
use Spiral\Core\ContainerInterface;
use Spiral\Http\Exceptions\RouteException;

/**
 * Declares ability to route.
 */
interface RouteInterface
{
    /**
     * Controller and action in route targets and createURL route name has to be separated like
     * that.
     */
    const SEPARATOR = '::';

    /**
     * @return string
     */
    public function getName();

    /**
     * Check if route matched with provided request.
     *
     * @param ServerRequestInterface $request
     * @param string                 $basePath
     * @return bool
     * @throws RouteException
     */
    public function match(ServerRequestInterface $request, $basePath = '/');

    /**
     * Execute route on given request. Has to be called after match method.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @param ContainerInterface     $container
     * @return ResponseInterface
     */
    public function perform(
        ServerRequestInterface $request,
        ResponseInterface $response,
        ContainerInterface $container
    );

    /**
     * Generate valid route URL using route name and set of parameters.
     *
     * @param array            $parameters Accepts only arrays at this moment.
     * @param string           $basePath
     * @param SlugifyInterface $slugify
     * @return UriInterface
     * @throws RouteException
     */
    public function createUri($parameters = [], $basePath = '/', SlugifyInterface $slugify = null);
}