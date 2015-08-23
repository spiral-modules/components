<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Http\Routing;

use Cocur\Slugify\SlugifyInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Spiral\Core\ContainerInterface;
use Spiral\Http\Exceptions\ClientException;
use Spiral\Http\Exceptions\RouteException;
use Spiral\Http\Exceptions\RouterException;
use Spiral\Http\MiddlewareInterface;

/**
 * Routers used by HttpDispatcher and other components for logical routing to controller actions.
 */
interface RouterInterface
{
    /**
     * @param ContainerInterface $container
     * @param RouteInterface[]   $routes Pre-defined array of routes (if were collected externally).
     */
    public function __construct(ContainerInterface $container, array $routes = []);

    /**
     * Valid endpoint for MiddlewarePipeline.
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws ClientException
     */
    public function __invoke(ServerRequestInterface $request);

    /**
     * @param RouteInterface $route
     */
    public function addRoute(RouteInterface $route);

    /**
     * Fetch route by it's name.
     *
     * @param string $route
     * @return RouteInterface
     * @throws RouterException
     */
    public function getRoute($route);

    /**
     * @return RouteInterface[]
     */
    public function getRoutes();

    /**
     * Route which did match with incoming request will be marked as active and can be fetched using
     * this method.
     *
     * @return RouteInterface
     */
    public function activeRoute();

    /**
     * Generate valid route URL using route name and set of parameters. Should support controller
     * and action name separated by ":" - in this case router should find appropriate route and
     * create url using it.
     *
     * @param string           $route Route name.
     * @param array            $parameters
     * @param SlugifyInterface $slugify
     * @return UriInterface
     * @throws RouterException
     * @throws RouteException
     */
    public function createUri($route, array $parameters = [], SlugifyInterface $slugify = null);
}