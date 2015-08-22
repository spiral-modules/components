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
use Spiral\Debug\Traits\BenchmarkTrait;
use Spiral\Debug\Traits\LoggerTrait;
use Spiral\Http\Exceptions\ClientException;
use Spiral\Http\Responses\EmptyResponse;
use Spiral\Http\Responses\HtmlResponse;
use Spiral\Http\Responses\JsonResponse;
use Spiral\Http\Routing\Traits\RouterTrait;
use Spiral\Views\ViewProviderInterface;
use Zend\Diactoros\Response\EmitterInterface;
use Zend\Diactoros\Response\SapiEmitter;
use Zend\Diactoros\ServerRequestFactory;

/**
 * Basic spiral Http Dispatcher implementation. Used for web based applications and can route
 * requests to controllers or custom endpoints.
 */
class HttpDispatcher extends Singleton implements
    DispatcherInterface,
    LoggerAwareInterface,
    HttpInterface
{
    /**
     * HttpDispatcher has embedded router and log it's errors.
     */
    use ConfigurableTrait, RouterTrait, LoggerTrait, BenchmarkTrait;

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
     * Initial server request.
     *
     * @var ServerRequestInterface
     */
    protected $request = null;

    /**
     * Set of middlewares to be applied for every request.
     *
     * @var callable[]|MiddlewareInterface[]
     */
    protected $middlewares = [];

    /**
     * @invisible
     * @var ContainerInterface
     */
    protected $container = null;

    /**
     * Required to render error pages.
     *
     * @var ViewProviderInterface
     */
    protected $views = null;

    /**
     * @param ConfiguratorInterface $configurator
     * @param ContainerInterface    $container
     */
    public function __construct(ConfiguratorInterface $configurator, ContainerInterface $container)
    {
        $this->config = $configurator->getConfig(static::CONFIG);
        $this->container = $container;

        $this->middlewares = $this->config['middlewares'];
    }

    /**
     * Views instance will be requested on demand (error) via container, method used to manually
     * specify it.
     *
     * @param ViewProviderInterface $views
     * @return $this
     */
    public function setViews(ViewProviderInterface $views)
    {
        $this->views = $views;

        return $this;
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
        //We need request to start, let's cast it
        $request = $this->request();

        //Now we can generate response using request
        $response = $this->perform($request);

        if (!empty($response)) {
            //Sending to client
            $this->dispatch($response);
        }
    }

    /**
     * Get initial request instance or create new one.
     *
     * @return ServerRequestInterface
     */
    public function request()
    {
        if (!empty($this->request)) {
            return $this->request;
        }

        //Isolation means that MiddlewarePipeline will handle exception using snapshot and not expose error
        return $this->request = ServerRequestFactory::fromGlobals(
            $_SERVER, $_GET, $_POST, $_COOKIE, $_FILES
        )->withAttribute('basePath', $this->basePath())->withAttribute(
            'isolated', $this->config['isolate']
        );
    }

    /**
     * Pass request thought all http middlewares to appropriate endpoint (will be selected based
     * on path). "activePath" argument will be added to request passed into middlewares.
     *
     * @param ServerRequestInterface $request
     * @param callable               $endpoint User specified endpoint.
     * @return ResponseInterface
     * @throws ClientException
     */
    public function perform(ServerRequestInterface $request, callable $endpoint = null)
    {
        if (empty($endpoint)) {
            //We are going to use default endpoint
            $endpoint = $this->router();
        }

        $pipeline = new MiddlewarePipeline(
            $this->container,
            $this->middlewares,
            $this->config['keepOutput']
        );

        $response = null;
        $benchmark = $this->benchmark('request', $request->getUri());
        try {
            //Configuring endpoint
            $pipeline = $pipeline->target($endpoint);

            return $pipeline->run($request);
        } finally {
            $this->benchmark($benchmark);
        }
    }

    /**
     * Dispatch response to client.
     *
     * @param ResponseInterface $response
     * @return null Specifically.
     */
    public function dispatch(ResponseInterface $response)
    {
        if (empty($this->emitter)) {
            $this->emitter = new SapiEmitter();
        }

        $this->emitter->emit($response, ob_get_level());

        return null;
    }

    /**
     * {@inheritdoc}
     *
     * @param bool                     $dispatch Snapshot will be automatically dispatched.
     * @param   ServerRequestInterface $request  Request caused snapshot.
     * @return ResponseInterface|null Depends of dispatching were requested.
     */
    public function handleSnapshot(
        SnapshotInterface $snapshot,
        $dispatch = true,
        ServerRequestInterface $request = null
    ) {
        if (empty($request)) {
            //Somewhere outside of dispatcher
            $request = $this->request();
        }

        if (!$this->config['exposeErrors']) {
            //Http was not allowed to show any error snapshot to client
            $response = $this->errorResponse(Response::SERVER_ERROR, $request);

            return $dispatch ? $this->dispatch($response) : $response;
        }

        if ($request->getHeaderLine('Accept') == 'application/json') {
            $context = ['status' => Response::SERVER_ERROR] + $snapshot->describe();
            $response = new JsonResponse($context, Response::SERVER_ERROR);
        } else {
            $response = new HtmlResponse($snapshot->render(), Response::SERVER_ERROR);
        }

        return $dispatch ? $this->dispatch($response) : $response;
    }

    /**
     * {@inheritdoc}
     */
    protected function createRouter()
    {
        return $this->container->construct($this->config['router']['class'], [
                'routes'     => $this->routes,
                'keepOutput' => $this->config['keepOutput']
            ] + $this->config['router']);
    }

    /**
     * Add error to http log.
     *
     * @param ClientException        $exception
     * @param ServerRequestInterface $request
     */
    public function logError(ClientException $exception, ServerRequestInterface $request)
    {
        $remoteAddr = '-undefined-';
        if (!empty($request->getServerParams()['REMOTE_ADDR'])) {
            $remoteAddr = $request->getServerParams()['REMOTE_ADDR'];
        }

        $this->logger()->warning(
            "{scheme}://{host}{path} caused the error {code} ({message}) by client {remote}.",
            [
                'scheme'  => $request->getUri()->getScheme(),
                'host'    => $request->getUri()->getHost(),
                'path'    => $request->getUri()->getPath(),
                'code'    => $exception->getCode(),
                'message' => $exception->getMessage() ?: '-not specified-',
                'remote'  => $remoteAddr
            ]
        );
    }

    /**
     * Create response for specifier error code, some responses can be have associated view files.
     *
     * @param int                    $code
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function errorResponse($code, ServerRequestInterface $request = null)
    {
        if (!empty($request) && $request->getHeaderLine('Accept') == 'application/json') {
            return new JsonResponse(['status' => $code], $code);
        }

        if (isset($this->config['httpErrors'][$code])) {
            //We can show custom error page
            return new HtmlResponse(
                $this->viewProvider()->render($this->config['httpErrors'][$code],
                    ['http' => $this]),
                $code
            );
        }

        return new EmptyResponse($code);
    }

    /**
     * Get associated views component or fetch it from container.
     *
     * @return ViewProviderInterface
     */
    private function viewProvider()
    {
        if (!empty($this->views)) {
            return $this->views;
        }

        return $this->views = $this->container->get(ViewProviderInterface::class);
    }
}