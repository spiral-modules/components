<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Http\Routing;

use Cocur\Slugify\Slugify;
use Cocur\Slugify\SlugifyInterface;
use Psr\Http\Message\ServerRequestInterface;
use Spiral\Core\ContainerInterface;
use Spiral\Core\CoreInterface;
use Spiral\Core\Exceptions\ControllerException;
use Spiral\Http\Exceptions\ClientException;
use Spiral\Http\MiddlewareInterface;
use Spiral\Http\MiddlewarePipeline;
use Spiral\Http\Uri;

/**
 * Base for all spiral routes.
 *
 * Routing format (examples given in context of Core->bootstrap() method and Route):
 *
 * Static routes.
 *      $this->http->route('profile-<id>', 'Controllers\UserController::showProfile');
 *      $this->http->route('profile-<id>', 'Controllers\UserController::showProfile');
 *
 * Dynamic actions:
 *      $this->http->route('account/<action>', 'Controllers\AccountController::<action>');
 *
 * Optional segments:
 *      $this->http->route('profile[/<id>]', 'Controllers\UserController::showProfile');
 *
 * This route will react on URL's like /profile/ and /profile/someSegment/
 *
 * To determinate your own pattern for segment use construction <segmentName:pattern>
 *      $this->http->route('profile[/<id:\d+>]', 'Controllers\UserController::showProfile');
 *
 * Will react only on /profile/ and /profile/1384978/
 *
 * You can use custom pattern for controller and action segments.
 * $this->http->route('users[/<action:edit|save|open>]', 'Controllers\UserController::<action>');
 *
 * Routes can be applied to URI host.
 * $this->http->route(
 *      '<username>.domain.com[/<action>[/<id>]]',
 *      'Controllers\UserController::<action>'
 * )->useHost();
 *
 * Routes can be used non only with controllers (no idea why you may need it):
 * $this->http->route('users', function ()
 * {
 *      return "This is users route.";
 * });
 */
abstract class AbstractRoute implements RouteInterface
{
    /**
     * Default segment pattern, this patter can be applied to controller names, actions and etc.
     */
    const DEFAULT_SEGMENT = '[^\/]+';

    /**
     * To execute actions.
     *
     * @invisible
     * @var CoreInterface
     */
    protected $core = null;

    /**
     * @var string
     */
    protected $name = '';

    /**
     * @var array
     */
    protected $middlewares = [];

    /**
     * Route pattern includes simplified regular expressing later compiled to real regexp.
     *
     * @var string
     */
    protected $pattern = '';

    /**
     * List of methods route should react to, by default all methods are allowed.
     *
     * @var array
     */
    protected $methods = [];

    /**
     * Default set of values to fill route matches and target pattern (if specified as pattern).
     *
     * @var array
     */
    protected $defaults = [];

    /**
     * If true route will be matched with URI host in addition to path. BasePath will be ignored.
     *
     * @var bool
     */
    protected $withHost = false;

    /**
     * Compiled route options, pattern and etc. Internal data.
     *
     * @invisible
     * @var array
     */
    protected $compiled = [];

    /**
     * Route matches, populated after match() method executed. Internal.
     *
     * @var array
     */
    protected $matches = [];

    /**
     * @param CoreInterface $core
     * @return $this
     */
    public function setCore(CoreInterface $core)
    {
        $this->core = $core;

        return $this;
    }

    /**
     * Set route name. Method should be executed before registering route in router.
     *
     * @param string $name
     * @return $this
     */
    public function name($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Declared route pattern.
     *
     * @return string
     */
    public function getPattern()
    {
        return $this->pattern;
    }

    /**
     * If true (default) route will be matched against path + URI host.
     *
     * @param bool $withHost
     * @return $this
     */
    public function withHost($withHost = true)
    {
        $this->withHost = $withHost;

        return $this;
    }

    /**
     * List of methods route should react to, by default all methods are passed.
     *
     * Example:
     * $route->only('GET');
     * $route->only(['POST', 'PUT']);
     *
     * @param array|string $method
     * @return $this
     */
    public function only($method)
    {
        $this->methods = is_array($method) ? $method : func_get_args();

        return $this;
    }

    /**
     * Update route defaults (new values will be merged with existed data).
     *
     * @param array $defaults
     * @return $this
     */
    public function defaults(array $defaults)
    {
        $this->defaults = $defaults + $this->defaults;

        return $this;
    }

    /**
     * Associated middleware with route.
     *
     * Example:
     * $route->with(new CacheMiddleware(100));
     * $route->with(ProxyMiddleware::class);
     *
     * @param callable|MiddlewareInterface $middleware
     * @return $this
     */
    public function with($middleware)
    {
        $this->middlewares[] = $middleware;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function match(ServerRequestInterface $request, $basePath = '/')
    {
        if (!empty($this->methods) && !in_array($request->getMethod(), $this->methods)) {
            return false;
        }

        if (empty($this->compiled)) {
            $this->compile();
        }

        $path = $request->getUri()->getPath();
        if (empty($path) || $path[0] !== '/') {
            $path = '/' . $path;
        }

        if ($this->withHost) {
            $uri = $request->getUri()->getHost() . $path;
        } else {
            $uri = substr($path, strlen($basePath));
        }

        if (preg_match($this->compiled['pattern'], rtrim($uri, '/'), $this->matches)) {
            //To get only named matches
            $this->matches = array_intersect_key($this->matches, $this->compiled['options']);
            $this->matches = array_merge(
                $this->compiled['options'],
                $this->defaults,
                $this->matches
            );

            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function perform(
        ServerRequestInterface $request,
        ContainerInterface $container,
        $keepOutput = false
    ) {
        $pipeline = new MiddlewarePipeline($container, $this->middlewares, $keepOutput);

        return $pipeline->target($this->createEndpoint($container))->run($request);
    }

    /**
     * {@inheritdoc}
     */
    public function createUri(
        array $parameters = [],
        $basePath = '/',
        SlugifyInterface $slugify = null
    ) {
        if (empty($this->compiled)) {
            $this->compile();
        }

        $parameters = array_map(
            [!empty($slugify) ? $slugify : new Slugify(), 'slugify'],
            $parameters + $this->defaults + $this->compiled['options']
        );

        //Uri without empty blocks
        $uri = strtr(
            \Spiral\interpolate($this->compiled['template'], $parameters, '<', '>'),
            ['[]' => '', '[/]' => '', '[' => '', ']' => '', '//' => '/']
        );

        $uri = new Uri(($this->withHost ? '' : $basePath) . $uri);

        //Getting additional query parameters
        if (!empty($queryParameters = array_diff_key($parameters, $this->compiled['options']))) {
            $uri->withQuery(http_build_query($queryParameters));
        }

        return $uri;
    }

    /**
     * Create callable route endpoint.
     *
     * @param ContainerInterface $container
     * @return callable
     */
    abstract protected function createEndpoint(ContainerInterface $container);

    /**
     * Internal helper used to create execute controller action using associated core instance.
     *
     * @param ContainerInterface $container
     * @param string             $controller
     * @param string             $action
     * @param array              $parameters
     * @return mixed
     * @throws ClientException
     */
    protected function callAction(
        ContainerInterface $container,
        $controller,
        $action,
        array $parameters = []
    ) {
        if (empty($this->core)) {
            $this->core = $container->get(CoreInterface::class);
        }

        try {
            return $this->core->callAction($controller, $action, $parameters);
        } catch (ControllerException $exception) {
            if (
                $exception->getCode() == ControllerException::BAD_ACTION
                || $exception->getCode() == ControllerException::NOT_FOUND
            ) {
                throw new ClientException(ClientException::NOT_FOUND, $exception->getMessage());
            }

            throw new ClientException(ClientException::BAD_DATA, $exception->getMessage());
        }
    }

    /**
     * Compile router pattern into valid regexp.
     */
    private function compile()
    {
        $replaces = ['/' => '\\/', '[' => '(?:', ']' => ')?', '.' => '\.'];

        $options = [];
        if (preg_match_all('/<(\w+):?(.*?)?>/', $this->pattern, $matches)) {
            $variables = array_combine($matches[1], $matches[2]);
            foreach ($variables as $name => $segment) {
                $segment = $segment ?: self::DEFAULT_SEGMENT;
                $replaces["<$name>"] = "(?P<$name>$segment)";
                $options[] = $name;
            }
        }

        $template = preg_replace('/<(\w+):?.*?>/', '<\1>', $this->pattern);
        $this->compiled = [
            'pattern'  => '/^' . strtr($template, $replaces) . '$/u',
            'template' => stripslashes(str_replace('?', '', $template)),
            'options'  => array_fill_keys($options, null)
        ];
    }
}