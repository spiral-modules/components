<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Http\Router;

use Cocur\Slugify\Slugify;
use Cocur\Slugify\SlugifyInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Spiral\Controllers\ControllerException;
use Spiral\Core\ContainerInterface;
use Spiral\Core\CoreInterface;
use Spiral\Http\ClientException;
use Spiral\Http\MiddlewareInterface;
use Spiral\Http\Uri;

abstract class AbstractRoute implements RouteInterface
{
    /**
     * Default segment pattern, this patter can be applied to controller names, actions and etc.
     */
    const DEFAULT_SEGMENT = '[^\/]+';

    /**
     * Default separator to split controller and action name in route target.
     */
    const CONTROLLER_SEPARATOR = '::';

    /**
     * CoreInterface used to execute actions handled by route.
     *
     * @var CoreInterface
     */
    protected $core = null;

    /**
     * Declared route name.
     *
     * @var string
     */
    protected $name = '';

    /**
     * Middlewares associated with route. You can always get access to parent route using route attribute
     * of server request.
     *
     * @var array
     */
    protected $middlewares = [];

    /**
     * Route pattern includes simplified regular expressing later compiled to real regexp. Pattern
     * with be applied to URI path with excluded active path value (to make routes work when application
     * located in folder and etc).
     *
     * @var string
     */
    protected $pattern = '';

    /**
     * List of methods route should react to, by default all methods are passed.
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
     * Result of regular expression. Matched can be used to fill target controller pattern or send
     * to controller method as arguments.
     *
     * @var array
     */
    protected $matches = [];

    /**
     * Set custom instance of CoreInterface to handle route controller and action.
     *
     * @param CoreInterface $core
     * @return $this
     */
    public function setCore(CoreInterface $core)
    {
        $this->core = $core;

        return $this;
    }

    /**
     * Set route name. This action should be performed BEFORE parent router will be created, in other
     * scenario route will be available under old name.
     *
     * @param string $name
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Alias for setName.
     *
     * @param string $name
     * @return $this
     */
    public function name($name)
    {
        return $this->setName($name);
    }

    /**
     * Get route name. Name is requires to correctly identify route inside router stack (to generate
     * url for example).
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get route pattern.
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
     * Set default values (will be merged with current default) to be used in generated target.
     *
     * @param array $defaults
     * @return $this
     */
    public function setDefaults(array $defaults)
    {
        $this->defaults = $defaults + $this->defaults;

        return $this;
    }

    /**
     * Alias for setDefaults.
     *
     * @param array $defaults
     * @return $this
     */
    public function defaults(array $defaults)
    {
        return $this->setDefaults($defaults);
    }

    /**
     * Associated inner middleware with route. Route can use middlewares previously registered in
     * Route by it's aliases.
     *
     * Example:
     *
     * $router->registerMiddleware('cache', new CacheMiddleware(100));
     * $route->with('cache');
     *
     * @param string|MiddlewareInterface|\Closure $middleware Inner middleware alias, instance or
     *                                                        closure.
     * @return $this
     */
    public function with($middleware)
    {
        $this->middlewares[] = $middleware;

        return $this;
    }

    /**
     * Helper method used to compile simplified route pattern to valid regular expression.
     *
     * We can cache results of this method in future.
     */
    protected function compile()
    {
        $replaces = ['/' => '\\/', '[' => '(?:', ']' => ')?', '.' => '\.'];

        $options = [];
        if (preg_match_all('/<(\w+):?(.*?)?>/', $this->pattern, $matches))
        {
            $variables = array_combine($matches[1], $matches[2]);
            foreach ($variables as $name => $segment)
            {
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

    /**
     * Check if route matched with provided request. Will check url pattern and pre-conditions.
     *
     * @param ServerRequestInterface $request
     * @param string                 $basePath
     * @return bool
     */
    public function match(ServerRequestInterface $request, $basePath = '/')
    {
        if (!empty($this->methods) && !in_array($request->getMethod(), $this->methods))
        {
            return false;
        }

        if (empty($this->compiled))
        {
            $this->compile();
        }

        $path = $request->getUri()->getPath();
        if (empty($path) || $path[0] !== '/')
        {
            $path = '/' . $path;
        }

        if ($this->withHost)
        {
            $uri = $request->getUri()->getHost() . $path;
        }
        else
        {
            $uri = substr($path, strlen($basePath));
        }

        if (preg_match($this->compiled['pattern'], rtrim($uri, '/'), $this->matches))
        {
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
     * Matches are populated after route matched request. Matched will include variable URL parts
     * merged with default values.
     *
     * @return array
     */
    public function getMatches()
    {
        return $this->matches;
    }

    /**
     * Create Uri using route parameters (will be merged with default values), route pattern and base
     * path.
     *
     * @param array            $parameters
     * @param string           $basePath
     * @param SlugifyInterface $slugify Instance to create url slugs. By default Slugify will be
     *                                  used.
     * @return UriInterface
     */
    public function createUri(array $parameters = [], $basePath = '/', SlugifyInterface $slugify = null)
    {
        if (empty($this->compiled))
        {
            $this->compile();
        }

        $parameters = array_map(
            [!empty($slugify) ? $slugify : $this->createSlugify(), 'slug'],
            $parameters + $this->defaults + $this->compiled['options']
        );

        //Uri without empty blocks
        $uri = strtr(
            \Spiral\interpolate($this->compiled['template'], $parameters, '<', '>'),
            ['[]' => '', '[/]' => '', '[' => '', ']' => '', '//' => '/']
        );

        $uri = new Uri(($this->withHost ? '' : $basePath) . $uri);

        //Getting additional query parameters
        if (!empty($queryParameters = array_diff_key($parameters, $this->compiled['options'])))
        {
            $uri->withQuery(http_build_query($queryParameters));
        }

        return $uri;
    }

    /**
     * Get instance of SlugifyInterface.
     *
     * @return SlugifyInterface
     */
    protected function createSlugify()
    {
        return new Slugify();
    }

    /**
     * Call controller action using CoreInteface.
     *
     * @param ContainerInterface $container
     * @param string             $controller
     * @param string             $action
     * @param array              $parameters
     * @return mixed
     * @throws ClientException
     */
    protected function callAction(ContainerInterface $container, $controller, $action, array $parameters = [])
    {
        if (empty($this->core))
        {
            $this->core = $container->get(CoreInterface::class);
        }

        try
        {
            return $this->core->callAction($controller, $action, $parameters);
        }
        catch (ControllerException $exception)
        {
            if ($exception->getCode() == ControllerException::BAD_ACTION)
            {
                throw new ClientException(ClientException::NOT_FOUND, $exception->getMessage());
            }

            //Something is wrong
            throw new ClientException(ClientException::BAD_DATA, $exception->getMessage());
        }
    }
}