<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Http\Router;

use Psr\Http\Message\ServerRequestInterface;
use Spiral\Core\ContainerInterface;
use Spiral\Http\HttpPipeline;

class DirectRoute extends AbstractRoute
{
    /**
     * Default controllers namespace.
     *
     * @var string
     */
    protected $namespace = '';

    /**
     * Default controller postfix.
     *
     * @var string
     */
    protected $postfix = '';

    /**
     * Controllers aliased by name, namespace and postfix will be ignored in this case.
     *
     * @var array
     */
    protected $controllers = [];

    /**
     * DirectRoute can route only to controllers, which means that pattern should always include
     * both <controller> and <action> segments. Route can be host specific.
     *
     * Usually DirectRoute used to create "general" route path without need to define route for every
     * controller action and etc. Having DirectRoute attached to Router as PrimaryRoute will allow
     * user to generate urls based on controller action name ($router->url("controller::action") or
     * $router->url("controller/action")).
     *
     * Examples:
     * new DirectRoute(
     *      "default",
     *      "(<controller>(/<action>(/<id>)))",
     *      "Controllers",
     *      "Controller",
     *      ["controller" => "home"]
     * );
     *
     * You can also create host depended routes.
     * $route = new DirectRoute(
     *      "default",
     *      "domain.com(/<controller>(/<action>(/<id>)))",
     *      "Controllers",
     *      "Controller",
     *      ["controller" => "home"]
     * );
     * $route->withHost();
     *
     * @param string $name
     * @param string $pattern
     * @param string $namespace   Default controllers namespace.
     * @param string $postfix     Default controller postfix.
     * @param array  $defaults    Default values (including default controller).
     * @param array  $controllers Controllers aliased by their name, namespace and postfix will be
     *                            ignored in this case.
     */
    public function __construct(
        $name,
        $pattern,
        $namespace,
        $postfix,
        array $defaults = [],
        array $controllers = []
    )
    {
        $this->name = $name;
        $this->pattern = $pattern;
        $this->namespace = $namespace;
        $this->postfix = $postfix;
        $this->defaults = $defaults;
        $this->controllers = $controllers;
    }

    /**
     * Create controller aliases, namespace and postfix will be ignored in this case.
     *
     * Example:
     * $route->controllers([
     *      "auth" => "Module\Authorization\AuthController"
     * ]);
     *
     * @param array $controllers
     * @return $this
     */
    public function controllers(array $controllers)
    {
        $this->controllers += $controllers;

        return $this;
    }

    /**
     * Perform route on given Request and return response.
     *
     * @param ServerRequestInterface $request
     * @param ContainerInterface     $container Container is required to get valid middleware instance
     *                                          and execute controllers in some cases.
     * @return mixed
     */
    public function perform(ServerRequestInterface $request, ContainerInterface $container)
    {
        $pipeline = new HttpPipeline($container, $this->middlewares);

        return $pipeline->target($this->createEndpoint($container))->run($request);
    }

    /**
     * Get callable route target.
     *
     * @param ContainerInterface $container
     * @return callable
     */
    protected function createEndpoint(ContainerInterface $container)
    {
        return function (ServerRequestInterface $request) use ($container)
        {
            $controller = $this->matches['controller'];

            if (isset($this->controllers[$controller]))
            {
                $controller = $this->controllers[$controller];
            }
            else
            {
                $controller = $this->namespace . '\\' . ucfirst($controller) . $this->postfix;
            }

            //Calling controller (using core resolved via container)
            return $this->callAction($container, $controller, $this->matches['action'], $this->matches);
        };
    }
}