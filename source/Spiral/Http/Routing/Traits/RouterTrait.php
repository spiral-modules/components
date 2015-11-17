<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Http\Routing\Traits;

use Spiral\Core\ContainerInterface;
use Spiral\Core\Exceptions\SugarException;
use Spiral\Http\Routing\Route;
use Spiral\Http\Routing\RouteInterface;
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
     * @var RouterInterface|null
     */
    private $router = null;

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
        if (!empty($this->router)) {
            return $this->router;
        }

        return $this->router = $this->createRouter();
    }

    /**
     * Add new route.
     *
     * @param RouteInterface $route
     * @return $this
     */
    public function addRoute(RouteInterface $route)
    {
        $this->router()->addRoute($route);

        return $this;
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
    public function route($pattern, $target, array $defaults = [])
    {
        $route = new Route(
            is_string($target) ? $target : uniqid('route', true),
            $pattern,
            $target,
            $defaults
        );

        $this->addRoute($route);

        return $route;
    }

    /**
     * Create router instance using container.
     *
     * @return RouterInterface
     * @throws SugarException
     */
    protected function createRouter()
    {
        if (empty($container = $this->container()) || !$container->has(RouterInterface::class)) {
            throw new SugarException(
                "Unable to create Router, container not set or binding is missing."
            );
        }

        //Let's create default router
        return $container->get(RouterInterface::class);
    }

    /**
     * @return ContainerInterface
     */
    abstract protected function container();
}