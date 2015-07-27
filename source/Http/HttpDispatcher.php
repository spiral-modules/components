<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Spiral\Core\ConfiguratorInterface;
use Spiral\Core\ContainerInterface;
use Spiral\Core\DispatcherInterface;
use Spiral\Core\Singleton;
use Spiral\Core\Traits\ConfigurableTrait;
use Spiral\Debug\Debugger;
use Spiral\Debug\Traits\LoggerTrait;
use Spiral\Http\Responses\EmptyResponse;
use Spiral\Http\Responses\HtmlResponse;
use Spiral\Http\Responses\JsonResponse;
use Spiral\Http\Router\Router;
use Spiral\Http\Router\Traits\RouterTrait;
use Spiral\Views\ViewManager;
use Spiral\Views\ViewsInterface;
use Zend\Diactoros\Response\EmitterInterface;
use Zend\Diactoros\Response\SapiEmitter;
use Zend\Diactoros\ServerRequestFactory;

class HttpDispatcher extends Singleton implements DispatcherInterface
{
    /**
     * Http required traits.
     */
    use ConfigurableTrait, RouterTrait, LoggerTrait;

    /**
     * Declares to IoC that component instance should be treated as singleton.
     */
    const SINGLETON = self::class;

    /**
     * Bigger streams will be send using chunks (if possible). Default 2Mb.
     */
    const STREAM_SIZE_THRESHOLD = 2097152;

    /**
     * Container is required to resolve endpoints and run middleware pipeline.
     *
     * @var ContainerInterface
     */
    protected $container = null;

    /**
     * ViewManager used to render error pages.
     *
     * @var ViewsInterface
     */
    protected $viewManager = null;

    /**
     * Debugger used to create snapshots.
     *
     * @var Debugger
     */
    protected $debugger = null;

    /**
     * Initial server request generated by spiral while starting HttpDispatcher.
     *
     * @var ServerRequestInterface
     */
    protected $request = null;

    /**
     * Set of middleware layers built to handle incoming Request and return Response. Middleware
     * can be represented as class, string (DI), closure or array (callable method). HttpDispatcher
     * layer middlewares will be called in start() method. This set of middleware(s) used to filter
     * http request and response on application layer.
     *
     * @var array|MiddlewareInterface[]|callable[]
     */
    protected $middlewares = [];

    /**
     * Endpoints is a set of middleware or callback used to handle some application parts separately
     * from application controllers and routes. Such Middlewares can perform their own routing,
     * mapping, render and etc and only have to return ResponseInterface object.
     *
     * You can use add() method to create new endpoint. Every endpoint should be specified as path
     * with / and in lower case.
     *
     * Example (in bootstrap):
     * $this->http->add('/forum', 'Vendor\Forum\Forum');
     *
     * P.S. Router middleware automatically assigned to base path of application.
     *
     * @var array|MiddlewareInterface[]
     */
    protected $endpoints = [];

    /**
     * Data emitter.
     *
     * @var EmitterInterface
     */
    protected $emitter = null;

    /**
     * New HttpDispatcher instance.
     *
     * @param ConfiguratorInterface $configurator
     * @param ContainerInterface    $container
     */
    public function __construct(ConfiguratorInterface $configurator, ContainerInterface $container)
    {
        $this->config = $configurator->getConfig($this);
        $this->container = $container;

        $this->middlewares = $this->config['middlewares'];
        $this->endpoints = $this->config['endpoints'];
    }

    /**
     * Due ViewManager will be created on demand (for performance reasons) - this method can used
     * to defined custom instance of renderer.
     *
     * @param ViewsInterface $viewManager
     */
    public function setViewManager(ViewsInterface $viewManager)
    {
        $this->viewManager = $viewManager;
    }

    /**
     * ViewManager instance.
     *
     * @return ViewsInterface
     */
    protected function getViewManager()
    {
        if (!empty($this->viewManager))
        {
            return $this->viewManager;
        }

        return $this->viewManager = $this->container->get(ViewManager::class);
    }

    /**
     * Due Debugger will be created on demand (for performance reasons) - this method can used
     * to defined custom instance of snapshot creator.
     *
     * @param Debugger $debugger
     */
    public function setDebugger(Debugger $debugger)
    {
        $this->debugger = $debugger;
    }

    /**
     * Debugger instance.
     *
     * @return Debugger
     */
    protected function getDebugger()
    {
        if (!empty($this->debugger))
        {
            return $this->debugger;
        }

        return $this->debugger = $this->container->get(Debugger::class);
    }

    /**
     * Application base path.
     *
     * @return string
     */
    public function getBasePath()
    {
        return $this->config['basePath'];
    }

    /**
     * Register new middleware to be executed at every request.
     *
     * Example (in bootstrap):
     * $this->http->middleware(new ProxyMiddleware());
     *
     * @param callable $middleware
     * @return $this
     */
    public function middleware($middleware)
    {
        $this->middlewares[] = $middleware;

        return $this;
    }

    /**
     * Register new endpoint or middleware inside HttpDispatcher. HttpDispatcher will execute such
     * enterpoint only with URI path matched to specified value. The rest of http flow will be
     * given to this enterpoint.
     *
     * Example (in bootstrap):
     * $this->http->add('/forum', 'Vendor\Forum\Forum');
     * $this->http->add('/blog', new Vendor\Module\Blog());
     *
     * @param string                              $path Http Uri path with / and in lower case.
     * @param string|callable|MiddlewareInterface $endpoint
     * @return $this
     */
    public function add($path, $endpoint)
    {
        $this->endpoints[$path] = $endpoint;

        return $this;
    }

    /**
     * Starting dispatcher.
     */
    public function start()
    {
        if (empty($this->endpoints[$this->getBasePath()]))
        {
            //Base path wasn't handled, let's attach our router
            $this->endpoints[$this->getBasePath()] = $this->createRouter();
        }

        //Become alive and die right after that
        $this->dispatch($this->perform($this->getRequest()));
    }

    /**
     * Get initial request.  This is untouched request object, all cookies will be encrypted and
     * other values will not be pre-processed.
     *
     * @return ServerRequestInterface
     */
    public function getRequest()
    {
        if (!empty($this->request))
        {
            return $this->request;
        }

        return $this->request = ServerRequestFactory::fromGlobals(
            $_SERVER,
            $_GET,
            $_POST,
            $_COOKIE,
            $_FILES
        )->withAttribute('basePath', $this->getBasePath());
    }

    /**
     * Create configured Router instance.
     *
     * @return Router
     */
    protected function createRouter()
    {
        return $this->container->get($this->config['router']['class'], [
                'routes' => $this->routes
            ] + $this->config['router']);
    }

    /**
     * Execute given request and return response. Request Uri will be passed thought Http routes
     * to find appropriate endpoint. By default this method will be called at the end of middleware
     * pipeline inside HttpDispatcher->start() method, however method can be called manually with
     * custom or altered request instance.
     *
     * Every request passed to perform method will be registered in Container scope under "request"
     * and class name binding.
     *
     * Http component middlewares will be applied to request and response.
     *
     * @param ServerRequestInterface $request
     * @return array|ResponseInterface
     * @throws ClientException
     */
    public function perform(ServerRequestInterface $request)
    {
        if (!$endpoint = $this->findEndpoint($request->getUri(), $activePath))
        {
            //This should never happen as request should be handled at least by Router middleware
            throw new ClientException(Response::SERVER_ERROR, 'Unable to select endpoint');
        }

        $pipeline = new HttpPipeline($this->container, $this->middlewares);

        return $pipeline->target($endpoint)->run(
            $request->withAttribute('activePath', $activePath)
        );
    }

    /**
     * Locate appropriate middleware endpoint based on Uri part.
     *
     * @param UriInterface $uri     Request Uri.
     * @param string       $uriPath Selected path.
     * @return null|MiddlewareInterface
     */
    protected function findEndpoint(UriInterface $uri, &$uriPath = null)
    {
        $uriPath = strtolower($uri->getPath());
        if (empty($uriPath))
        {
            $uriPath = '/';
        }
        elseif ($uriPath[0] !== '/')
        {
            $uriPath = '/' . $uriPath;
        }

        if (isset($this->endpoints[$uriPath]))
        {
            return $this->endpoints[$uriPath];
        }
        else
        {
            foreach ($this->endpoints as $path => $middleware)
            {
                if (strpos($uriPath, $path) === 0)
                {
                    $uriPath = $path;

                    return $middleware;
                }
            }
        }

        return null;
    }

    /**
     * Set alternate response emitter to use.
     *
     * @param EmitterInterface $emitter
     */
    public function setEmitter(EmitterInterface $emitter)
    {
        $this->emitter = $emitter;
    }

    /**
     * Dispatch response to client. Selected emitter will be used.
     *
     * @param ResponseInterface $response
     */
    public function dispatch(ResponseInterface $response)
    {
        if (empty($this->emitter))
        {
            $this->emitter = new SapiEmitter();
        }

        $this->emitter->emit($response, ob_get_level());
    }

    /**
     * Every application dispatcher should know how to handle exception.
     *
     * @param \Exception $exception
     * @return mixed
     */
    public function handleException(\Exception $exception)
    {
        if ($exception instanceof ClientException)
        {
            $this->logClientError($exception);
            $this->dispatch($this->errorResponse($exception->getCode()));

            return;
        }

        //We need snapshot
        $snapshot = $this->getDebugger()->createSnapshot($exception, true);

        if (!$this->config['exposeErrors'])
        {
            $this->dispatch($this->errorResponse(Response::SERVER_ERROR));

            return;
        }

        if ($this->request->getHeaderLine('Accept') == 'application/json')
        {
            $this->dispatch(new JsonResponse(
                ['status' => Response::SERVER_ERROR] + $snapshot->packException(),
                Response::SERVER_ERROR
            ));

            return;
        }

        $this->dispatch(new HtmlResponse($snapshot->render(), Response::SERVER_ERROR));
    }

    /**
     * Log client error.
     *
     * @param ClientException $exception
     */
    protected function logClientError(ClientException $exception)
    {
        $uri = $this->request->getUri();

        $remoteAddr = '-undefined-';
        if (!empty($this->request->getServerParams()['REMOTE_ADDR']))
        {
            $remoteAddr = $this->request->getServerParams()['REMOTE_ADDR'];
        }

        $this->logger()->warning(
            "{scheme}://{host}{path} caused the error {code} ({message}) by client {remote}.",
            [
                'scheme'  => $uri->getScheme(),
                'host'    => $uri->getHost(),
                'path'    => $uri->getPath(),
                'code'    => $exception->getCode(),
                'message' => $exception->getMessage() ?: '-not specified-',
                'remote'  => $remoteAddr
            ]
        );
    }

    /**
     * Get response dedicated to represent server or client error.
     *
     * @param int $code
     * @return ResponseInterface
     */
    protected function errorResponse($code)
    {
        if ($this->request->getHeaderLine('Accept') == 'application/json')
        {
            return new JsonResponse(['status' => $code], $code);
        }

        if (isset($this->config['httpErrors'][$code]))
        {
            //We can show custom error page
            return new HtmlResponse(
                $this->getViewManager()->render($this->config['httpErrors'][$code]),
                $code
            );
        }

        return new EmptyResponse($code);
    }
}