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
use Psr\Log\LoggerAwareInterface;
use Spiral\Core\ConfiguratorInterface;
use Spiral\Core\ContainerInterface;
use Spiral\Core\DispatcherInterface;
use Spiral\Core\Singleton;
use Spiral\Core\Traits\ConfigurableTrait;
use Spiral\Debug\SnapshotInterface;
use Spiral\Debug\Traits\LoggerTrait;
use Spiral\Http\Exceptions\ClientException;
use Spiral\Http\Responses\EmptyResponse;
use Spiral\Http\Responses\HtmlResponse;
use Spiral\Http\Responses\JsonResponse;
use Spiral\Http\Routing\Traits\RouterTrait;
use Spiral\Views\ViewsInterface;
use Zend\Diactoros\Response\EmitterInterface;
use Zend\Diactoros\Response\SapiEmitter;
use Zend\Diactoros\ServerRequestFactory;

/**
 * Basic spiral Http core implementation. Used for web based applications and can route requests
 * to controllers or custom endpoints.
 */
class HttpDispatcher extends Singleton implements DispatcherInterface, LoggerAwareInterface
{
    /**
     * HttpDispatcher has embedded router and log it's errors.
     */
    use ConfigurableTrait, RouterTrait, LoggerTrait;

    /**
     * Declares to IoC that component instance should be treated as singleton.
     */
    const SINGLETON = self::class;

    /**
     * Configuration section.
     */
    const CONFIG = 'http';

    /**
     * @var EmitterInterface
     */
    private $emitter = null;

    /**
     * @var ContainerInterface
     */
    protected $container = null;

    /**
     * Required to render error pages.
     *
     * @var ViewsInterface
     */
    protected $views = null;

    /**
     * Initial server request.
     *
     * @var ServerRequestInterface
     */
    protected $request = null;

    /**
     * Endpoint is callable class or closure used to handle part of application logic. Router
     * middleware automatically assigned to base path of application if nothing else was specified.
     *
     * @see endpoint()
     * @var callable[]|MiddlewareInterface[]
     */
    protected $endpoints = [];

    /**
     * Set of middlewares to be applied for every request.
     *
     * @var callable[]|MiddlewareInterface[]
     */
    protected $middlewares = [];

    /**
     * @param ConfiguratorInterface $configurator
     * @param ContainerInterface    $container
     */
    public function __construct(ConfiguratorInterface $configurator, ContainerInterface $container)
    {
        $this->config = $configurator->getConfig(static::CONFIG);
        $this->container = $container;

        $this->endpoints = $this->config['endpoints'];
        $this->middlewares = $this->config['middlewares'];
    }

    /**
     * Views instance will be requested on demand, method used to manually specify it.
     *
     * @param ViewsInterface $views
     * @return $this
     */
    public function setViews(ViewsInterface $views)
    {
        $this->views = $views;

        return $this;
    }

    /**
     * Get associated views component or fetch it from container.
     *
     * @return ViewsInterface
     */
    protected function views()
    {
        if (!empty($this->views))
        {
            return $this->views;
        }

        return $this->views = $this->container->get(ViewsInterface::class);
    }

    /**
     * @param EmitterInterface $emitter
     */
    public function setEmitter(EmitterInterface $emitter)
    {
        $this->emitter = $emitter;
    }

    /**
     * Application base path.
     *
     * @return string
     */
    public function basePath()
    {
        return $this->config['basePath'];
    }

    /**
     * Associate new endpoint to specific path.
     *
     * Example (in bootstrap):
     * $this->http->add('/forum', 'Vendor\Forum\Forum');
     * $this->http->add('/blog', new Vendor\Module\Blog());
     *
     * @see $endpoints
     * @param string                       $path Http Uri path with / and in lower case.
     * @param callable|MiddlewareInterface $endpoint
     * @return $this
     */
    public function endpoint($path, $endpoint)
    {
        $this->endpoints[$path] = $endpoint;

        return $this;
    }

    /**
     * Add new middleware into chain.
     *
     * Example (in bootstrap):
     * $this->http->middleware(new ProxyMiddleware());
     *
     * @param callable|MiddlewareInterface $middleware
     * @return $this
     */
    public function middleware($middleware)
    {
        $this->middlewares[] = $middleware;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function start()
    {
        if (empty($this->endpoints[$this->basePath()]))
        {
            //Base path wasn't handled, let's attach our router
            $this->endpoints[$this->basePath()] = $this->router();
        }

        //Become alive and die right after that
        $this->dispatch($this->perform($this->request()));
    }

    /**
     * Get initial request instance or create new one.
     *
     * @return ServerRequestInterface
     */
    public function request()
    {
        if (!empty($this->request))
        {
            return $this->request;
        }

        return $this->request = ServerRequestFactory::fromGlobals(
            $_SERVER, $_GET, $_POST, $_COOKIE, $_FILES
        )->withAttribute('basePath', $this->basePath());
    }

    /**
     * Pass request thought all http middlewares to appropriate endpoint (will be selected based
     * on path). "activePath" argument will be added to request passed into middlewares.
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws ClientException
     */
    public function perform(ServerRequestInterface $request)
    {
        if (!$endpoint = $this->findEndpoint($request->getUri(), $activePath))
        {
            //This should never happen as request should be handled at least by Router middleware
            throw new ClientException(Response::SERVER_ERROR, 'Unable to select endpoint.');
        }

        $pipeline = new MiddlewarePipeline($this->container, $this->middlewares);

        return $pipeline->target($endpoint)->run($request->withAttribute('activePath', $activePath));
    }

    /**
     * Dispatch response to client.
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
     * {@inheritdoc}
     */
    public function handleException(\Exception $exception)
    {
        if ($exception instanceof ClientException)
        {
            $this->logError($exception);
            $this->dispatch($this->errorResponse($exception->getCode()));

            return;
        }

        $this->dispatch($this->errorResponse(Response::SERVER_ERROR));
    }

    /**
     * {@inheritdoc}
     */
    public function handleSnapshot(SnapshotInterface $snapshot)
    {
        if (!$this->config['exposeErrors'])
        {
            $this->handleException($snapshot->getException());

            return;
        }

        if ($this->request->getHeaderLine('Accept') == 'application/json')
        {
            $context = ['status' => Response::SERVER_ERROR] + $snapshot->describe();
            $response = new JsonResponse($context, Response::SERVER_ERROR);
        }
        else
        {
            $response = new HtmlResponse($snapshot->render(), Response::SERVER_ERROR);
        }

        $this->dispatch($response);
    }

    /**
     * {@inheritdoc}
     */
    protected function createRouter()
    {
        return $this->container->get($this->config['router']['class'], [
                'routes' => $this->routes
            ] + $this->config['router']);
    }

    /**
     * Find endpoint to be executed using uri.
     *
     * @param UriInterface $uri
     * @param string       $uriPath Selected path, runtime.
     * @return null|callable
     */
    protected function findEndpoint(UriInterface $uri, &$uriPath = null)
    {
        if (empty($uriPath = strtolower($uri->getPath())))
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
     * Add error to http log.
     *
     * @param ClientException $exception
     */
    private function logError(ClientException $exception)
    {
        $remoteAddr = '-undefined-';
        if (!empty($this->request->getServerParams()['REMOTE_ADDR']))
        {
            $remoteAddr = $this->request->getServerParams()['REMOTE_ADDR'];
        }

        $this->logger()->warning(
            "{scheme}://{host}{path} caused the error {code} ({message}) by client {remote}.",
            [
                'scheme'  => $this->request()->getUri()->getScheme(),
                'host'    => $this->request()->getUri()->getHost(),
                'path'    => $this->request()->getUri()->getPath(),
                'code'    => $exception->getCode(),
                'message' => $exception->getMessage() ?: '-not specified-',
                'remote'  => $remoteAddr
            ]
        );
    }

    /**
     * Create response for specifier error code, some responses can be have associated view files.
     *
     * @param int $code
     * @return ResponseInterface
     */
    private function errorResponse($code)
    {
        if ($this->request->getHeaderLine('Accept') == 'application/json')
        {
            return new JsonResponse(['status' => $code], $code);
        }

        if (isset($this->config['httpErrors'][$code]))
        {
            //We can show custom error page
            return new HtmlResponse(
                $this->views()->render($this->config['httpErrors'][$code], ['http' => $this]),
                $code
            );
        }

        return new EmptyResponse($code);
    }
}