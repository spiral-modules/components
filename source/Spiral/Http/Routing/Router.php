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
use Spiral\Core\ContainerInterface;
use Spiral\Http\Exceptions\ClientException;
use Spiral\Http\Exceptions\RouterException;

/**
 * Spiral implementation of RouterInterface.
 */
class Router implements RouterInterface
{
    /**
     * Default name for primary route.
     */
    const DEFAULT_ROUTE = 'primary';

    /**
     * @var RouteInterface[]
     */
    private $routes = [];

    /**
     * Primary route (fallback if no routes work).
     *
     * @var RouteInterface
     */
    private $defaultRoute = null;

    /**
     * Every route should be executed in a context of base path.
     *
     * @var string
     */
    private $basePath = '/';

    /**
     * Active route instance, this value will be populated only after router successfully handled
     * incoming request.
     *
     * @var RouteInterface|null
     */
    private $activeRoute = null;

    /**
     * @invisible
     * @var ContainerInterface
     */
    protected $container = null;

    /**
     * {@inheritdoc}
     *
     * @param RouteInterface|array $default Default route or options to construct instance of
     *                                      DirectRoute.
     * @param bool                 $keepOutput
     * @throws RouterException
     */
    public function __construct(
        ContainerInterface $container,
        array $routes = [],
        $basePath = '/',
        $default = []
    ) {
        $this->basePath = $basePath;

        $this->container = $container;
        foreach ($routes as $route) {
            if (!$route instanceof RouteInterface) {
                throw new RouterException("Routes should be array of Route instances.");
            }

            if ($route->getName() == self::DEFAULT_ROUTE) {
                $default = $route;
                continue;
            }

            //Name aliasing is required to perform URL generation later.
            $this->routes[] = $route;
        }

        if ($default instanceof RouteInterface) {
            $this->defaultRoute = $default;

            return;
        }

        if (!empty($default) && is_array($default)) {
            $this->defaultRoute = new DirectRoute(
                self::DEFAULT_ROUTE,
                $default['pattern'],
                $default['namespace'],
                $default['postfix'],
                $default['defaults'],
                $default['controllers']
            );
        }
    }

    /**
     * @param RouteInterface $route
     */
    public function setDefaultRoute(RouteInterface $route)
    {
        $this->defaultRoute = $route;
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response)
    {
        //Open router scope
        $outerRouter = $this->container->replace(self::class, $this);

        if (empty($this->activeRoute = $this->findRoute($request, $this->basePath))) {
            throw new ClientException(ClientException::NOT_FOUND);
        }

        //Default routes will understand about keepOutput
        $response = $this->activeRoute->perform(
            $request->withAttribute('route', $this->activeRoute),
            $response,
            $this->container
        );

        //Close router scope
        $this->container->restore($outerRouter);

        return $response;
    }

    /**
     * Find route matched for given request.
     *
     * @param ServerRequestInterface $request
     * @param string                 $basePath
     * @return null|RouteInterface
     */
    protected function findRoute(ServerRequestInterface $request, $basePath)
    {
        foreach ($this->routes as $route) {
            if ($route->match($request, $basePath)) {
                return $route;
            }
        }

        if ($this->defaultRoute->match($request, $basePath)) {
            return $this->defaultRoute;
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function addRoute(RouteInterface $route)
    {
        $this->routes[] = $route;
    }

    /**
     * {@inheritdoc}
     */
    public function getRoute($name)
    {
        foreach ($this->routes as $route) {
            if ($route->getName() == $name) {
                return $route;
            }
        }

        throw new RouterException("Undefined route '{$name}'.");
    }

    /**
     * {@inheritdoc}
     */
    public function getRoutes()
    {
        return $this->routes;
    }

    /**
     * {@inheritdoc}
     */
    public function activeRoute()
    {
        return $this->activeRoute;
    }

    /**
     * {@inheritdoc}
     */
    public function createUri($route, $parameters = [], SlugifyInterface $slugify = null)
    {
        if (isset($this->routes[$route])) {
            return $this->routes[$route]->createUri($parameters, $this->basePath, $slugify);
        }

        //Will be handled via default route where route name is specified as controller::action
        if (strpos($route, RouteInterface::SEPARATOR) === false && strpos($route, '/') === false) {
            throw new RouterException(
                "Unable to locate route or use default route with controller::action pattern."
            );
        }

        //We can fetch controller and action names from url
        list($controller, $action) = explode(
            RouteInterface::SEPARATOR, str_replace('/', RouteInterface::SEPARATOR, $route)
        );

        return $this->defaultRoute->createUri(
            compact('controller', 'action') + $parameters,
            $this->basePath,
            $slugify
        );
    }
}