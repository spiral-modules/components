<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Http\Router;

use Spiral\Http\MiddlewareInterface;
use Zend\Diactoros\Response\RedirectResponse;

interface RouterInterface extends MiddlewareInterface
{
    /**
     * Add new Route instance to router stack, route has to be added before router handled request.
     *
     * @param RouteInterface $route
     */
    public function addRoute(RouteInterface $route);

    /**
     * All registered routes.
     *
     * @return RouteInterface[]
     */
    public function getRoutes();

    /**
     * Get route by name.
     *
     * @param string $route
     * @return RouteInterface
     * @throws RouterException
     */
    public function getRoute($route);

    /**
     * Get currently active route, this value will be populated only after router successfully handled
     * incoming request.
     *
     * @return RouteInterface|null
     */
    public function activeRoute();

    /**
     * Generate url using route name and set of provided parameters. Parameters will be automatically
     * injected to route pattern and prefixed with activePath value.
     *
     * You can enter controller::action type route, in this case appropriate controller and action
     * will be injected into default route as controller and action parameters accordingly. Default
     * route should be instance of spiral DirectRoute or compatible.
     *
     * Example:
     * $this->router->url('post::view', ['id' => 1]);
     * $this->router->url('post/view', ['id' => 1]);
     *
     * @param string $route      Route name.
     * @param array  $parameters Route parameters including controller name, action and etc.
     * @return string
     * @throws RouterException
     */
    public function url($route, array $parameters = []);

    /**
     * Generate redirect based on url rendered using specified route pattern.
     *
     * You can enter controller::action type route, in this case appropriate controller and action
     * will be injected into default route as controller and action parameters accordingly. Default
     * route should be instance of spiral DirectRoute or compatible.
     *
     * Example:
     * return $this->router->redirect('post::view', ['id' => 1]);
     *
     * @param string $route      Route name.
     * @param array  $parameters Route parameters including controller name, action and etc.
     * @return RedirectResponse
     */
    public function redirect($route, array $parameters = []);
}