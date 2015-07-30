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
use Psr\Http\Message\ServerRequestInterface;
use Spiral\Core\ContainerInterface;
use Spiral\Http\Exceptions\ClientException;
use Spiral\Http\Exceptions\InvalidArgumentException;
use Spiral\Http\Exceptions\RouterException;

/**
 * Spiral implementation of RouterInterface.
 */
class Router implements RouterInterface
{
    /**
     * Internal name for primary (default) route. Primary route used to resolve url and perform controller
     * based routing in cases where no other route found.
     *
     * Primary route should support <controller> and <action> parameters. Basically this is multi
     * controller route. Primary route should be instance of spiral DirectRoute or compatible.
     */
    const DEFAULT_ROUTE = 'default';

    /**
     * @invisible
     * @var ContainerInterface
     */
    protected $container = null;

    /**
     * @var RouteInterface[]
     */
    protected $routes = [];

    /**
     * Every route should be executed in a context of active path.
     *
     * @var string
     */
    protected $activePath = '/';

    /**
     * Active route instance, this value will be populated only after router successfully handled
     * incoming request.
     *
     * @var RouteInterface|null
     */
    protected $activeRoute = null;

    /**
     * {@inheritdoc}
     *
     * @param RouteInterface|array $default Default route or options to construct instance
     *                                      of DirectRoute.
     *
     * @throws InvalidArgumentException
     */
    public function __construct(ContainerInterface $container, array $routes = [], array $default = [])
    {
        $this->container = $container;
        foreach ($routes as $route)
        {
            if (!$route instanceof RouteInterface)
            {
                throw new InvalidArgumentException("Routes should be array of Route instances.");
            }

            //Name aliasing is required to perform URL generation later.
            $this->routes[$route->getName()] = $route;
        }

        if ($default instanceof RouteInterface)
        {
            $this->routes[self::DEFAULT_ROUTE] = $default;

            return;
        }

        if (!isset($this->routes[self::DEFAULT_ROUTE]) && !empty($default))
        {
            $this->routes[self::DEFAULT_ROUTE] = new DirectRoute(
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
     * {@inheritdoc}
     *
     * @throws ClientException
     */
    public function __invoke(ServerRequestInterface $request, \Closure $next = null, $context = null)
    {
        //Open router scope
        $outerRouter = $this->container->replace(self::class, $this);

        $this->activePath = $request->getAttribute('activePath', $this->activePath);
        if (!$this->activeRoute = $this->findRoute($request, $this->activePath))
        {
            throw new ClientException(ClientException::NOT_FOUND);
        }

        $response = $this->activeRoute->perform(
            $request->withAttribute('route', $this->activeRoute), $this->container
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
        foreach ($this->routes as $route)
        {
            if ($route->match($request, $basePath))
            {
                return $route;
            }
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
    public function getRoute($route)
    {
        if (!isset($this->routes[$route]))
        {
            throw new RouterException("Undefined route '{$route}'.");
        }

        return $this->routes[$route];
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
    public function createUri($route, array $parameters = [], SlugifyInterface $slugify = null)
    {
        if (isset($this->routes[$route]))
        {
            return $this->routes[$route]->createUri($parameters, $this->activePath, $slugify);
        }

        //Will be handled via default route where route name is specified as controller::action
        if (strpos($route, RouteInterface::SEPARATOR) == false && strpos($route, '/') === false)
        {
            throw new RouterException(
                "Unable to locate route or use default route with controller::action pattern."
            );
        }

        //We can fetch controller and action names from url
        list($controller, $action) = explode(
            RouteInterface::SEPARATOR, str_replace('/', RouteInterface::SEPARATOR, $route)
        );

        return $this->routes[self::DEFAULT_ROUTE]->createUri(
            compact('controller', 'action') + $parameters,
            $this->activePath,
            $slugify
        );
    }
}