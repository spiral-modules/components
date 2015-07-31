<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Http\Routing\Traits;

use Spiral\Http\Exceptions\RouterException;
use Spiral\Http\Routing\Route;
use Spiral\Core\ContainerInterface;
use Spiral\Http\Routing\RouteInterface;
use Spiral\Http\Routing\Router;
use Spiral\Http\Routing\RouterInterface;

/**
 * Provides set of method used to create and populate associated router. Can be used inside http
 * dispatcher or custom endpoint implementations.
 *
 * Default router creation requires container to be set!
 */
trait RouterTrait
{
    /**
     * @var RouteInterface[]
     */
    protected $routes = [];

    /**
     * @var Router|null
     */
    protected $router = null;

    /**
     * @return ContainerInterface
     */
    abstract public function container();

    /**
     * Set custom router implementation.
     *
     * @param RouterInterface $router
     */
    public function setRouter(RouterInterface $router)
    {
        $this->router = $router;
    }

    /**
     * Get associated router or create new one.
     *
     * @return RouterInterface
     */
    public function router()
    {
        if (!empty($this->router))
        {
            return $this->router;
        }

        return $this->router = $this->createRouter();
    }

    /**
     * Add new route.
     *
     * @param RouteInterface $route
     */
    public function addRoute(RouteInterface $route)
    {
        $this->routes[] = $route;
        !empty($this->router) && $this->router->addRoute($route);
    }

    /**
     * Shortcut to register new Route instance in associated router.
     *
     * @see AbstractRoute
     * @see Route
     * @param string          $pattern Route pattern.
     * @param string|callable $target  Route target.
     * @param array           $defaults
     * @return Route
     */
    public function route($pattern, $target = null, array $defaults = [])
    {
        $name = is_string($target) ? $target : uniqid('route', true);
        $this->addRoute($route = new Route($name, $pattern, $target, $defaults));

        return $route;
    }
    
    /**
     * Create router instance using container.
     *
     * @return RouterInterface
     * @throws RouterException
     */
    protected function createRouter()
    {
        if (empty($container = $this->container()))
        {
            throw new RouterException("Unable to create default router, default container not set.");
        }

        return new Router($container, $this->routes);
    }
}