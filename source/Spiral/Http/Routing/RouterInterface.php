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
use Psr\Http\Message\UriInterface;
use Spiral\Core\ContainerInterface;
use Spiral\Http\Exceptions\RouteException;
use Spiral\Http\Exceptions\RouterException;
use Spiral\Http\MiddlewareInterface;
use Zend\Diactoros\Response\RedirectResponse;

/**
 * Routers used by HttpDispatcher and endpoints for logical routing to controller actions.
 */
interface RouterInterface extends MiddlewareInterface
{
    /**
     * @param ContainerInterface $container
     * @param RouteInterface[]   $routes Pre-defined array of routes (if were collected externally).
     */
    public function __construct(ContainerInterface $container, array $routes = []);

    /**
     * @param RouteInterface $route
     */
    public function addRoute(RouteInterface $route);

    /**
     * @return RouteInterface[]
     */
    public function getRoutes();

    /**
     * Fetch route by it's name.
     *
     * @param string $route
     * @return RouteInterface
     * @throws RouterException
     */
    public function getRoute($route);

    /**
     * Route which did match with incoming request will be marked as active and can be fetched using
     * this method.
     *
     * @return RouteInterface
     */
    public function activeRoute();

    /**
     * Generate valid route URL using route name and set of parameters.
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