<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Http\Routing;

use Psr\Http\Message\ServerRequestInterface;
use Spiral\Core\ContainerInterface;
use Spiral\Http\MiddlewarePipeline;

/**
 * {@inheritdoc} General purpose route.
 */
class Route extends AbstractRoute
{
    /**
     * Use this string as your target action to resolve action from routed URL.
     *
     * Example: new Route('name', 'userPanel/<action>', 'Controllers\UserPanel::<action>');
     *
     * Attention, you can't route controllers this way, use DirectRoute for such purposes.
     */
    const DYNAMIC_ACTION = '<action>';

    /**
     * Route target in a form of callable or string pattern.
     *
     * @var callable|string
     */
    protected $target = null;

    /**
     * New Route instance.
     *
     * @param string          $name
     * @param string          $pattern
     * @param string|callable $target Route target.
     * @param array           $defaults
     */
    public function __construct($name, $pattern, $target, array $defaults = [])
    {
        $this->name = $name;
        $this->pattern = $pattern;
        $this->target = $target;
        $this->defaults = $defaults;
    }

    /**
     * {@inheritdoc}
     */
    public function perform(ServerRequestInterface $request, ContainerInterface $container)
    {
        $pipeline = new MiddlewarePipeline($container, $this->middlewares);

        return $pipeline->target($this->createEndpoint($container))->run($request);
    }

    /**
     * Create callable route endpoint.
     *
     * @param ContainerInterface $container
     * @return callable
     */
    protected function createEndpoint(ContainerInterface $container)
    {
        if (is_object($this->target) || is_array($this->target))
        {
            return $this->target;
        }

        if (is_string($this->target) && strpos($this->target, self::SEPARATOR) === false)
        {
            //Middleware
            return $container->get($this->target);
        }

        $route = $this;

        return function (ServerRequestInterface $request) use ($container, $route)
        {
            list($controller, $action) = explode(self::SEPARATOR, $route->target);

            if ($action == self::DYNAMIC_ACTION)
            {
                $action = $route->matches['action'];
            }

            return $route->callAction($container, $controller, $action, $route->matches);
        };
    }
}