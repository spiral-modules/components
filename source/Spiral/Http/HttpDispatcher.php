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
use Psr\Log\LoggerAwareInterface;
use Spiral\Core\ConfiguratorInterface;
use Spiral\Core\Container\SingletonInterface;
use Spiral\Core\ContainerInterface;
use Spiral\Core\DispatcherInterface;
use Spiral\Core\Traits\ConfigurableTrait;
use Spiral\Core\Traits\SingletonTrait;
use Spiral\Debug\SnapshotInterface;
use Spiral\Debug\Traits\LoggerTrait;
use Spiral\Http\Exceptions\ClientException;
use Spiral\Http\Exceptions\ClientExceptions\ServerErrorException;
use Spiral\Http\Responses\ExceptionResponse;
use Spiral\Http\Responses\HtmlResponse;
use Spiral\Http\Responses\JsonResponse;
use Spiral\Http\Routing\Traits\RouterTrait;
use Spiral\Views\ViewsInterface;
use Zend\Diactoros\ServerRequestFactory;

/**
 * Basic spiral Http Dispatcher implementation. Used for web based applications and can route
 * requests to controllers or custom endpoints.
 */
class HttpDispatcher extends HttpCore implements
    DispatcherInterface,
    SingletonInterface,
    LoggerAwareInterface
{
    /**
     * HttpDispatcher has embedded router and log it's errors.
     */
    use ConfigurableTrait, RouterTrait, LoggerTrait, SingletonTrait;

    /**
     * Declares to IoC that component instance should be treated as singleton.
     */
    const SINGLETON = self::class;

    /**
     * Configuration section.
     */
    const CONFIG = 'http';

    /**
     * Initial server request.
     *
     * @var ServerRequestInterface
     */
    protected $request = null;

    /**
     * Required to render error pages.
     *
     * @var ViewsInterface
     */
    protected $views = null;

    /**
     * @param ConfiguratorInterface $configurator
     * @param ContainerInterface    $container
     */
    public function __construct(ConfiguratorInterface $configurator, ContainerInterface $container)
    {
        $this->config = $configurator->getConfig(static::CONFIG);

        parent::__construct(
            $container,
            $this->config['middlewares'],
            !empty($this->config['endpoint']) ? $this->config['endpoint'] : null
        );
    }

    /**
     * Views instance will be requested on demand (error) via container, method used to manually
     * specify it.
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
     * Application base path.
     *
     * @return string
     */
    public function basePath()
    {
        return $this->config['basePath'];
    }

    /**
     * {@inheritdoc}
     */
    public function start()
    {
        //Now we can generate response using request
        $response = $this->perform(
            $this->request(),
            $this->response(),
            $this->endpoint()
        );

        if (!empty($response)) {
            //Sending to client
            $this->dispatch($response);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @param bool                   $dispatch Snapshot will be automatically dispatched.
     * @param ServerRequestInterface $request  Request caused snapshot.
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
            $response = $this->exceptionResponse(
                new ServerErrorException(),
                $request
            );
        } else {
            if ($request->getHeaderLine('Accept') == 'application/json') {
                $context = ['status' => Response::SERVER_ERROR] + $snapshot->describe();
                $response = new JsonResponse(
                    $context,
                    Response::SERVER_ERROR
                );
            } else {
                $response = new HtmlResponse(
                    $snapshot->render(),
                    Response::SERVER_ERROR
                );
            }
        }

        if (!$dispatch) {
            return $response;
        }

        return $this->dispatch($response);
    }

    /**
     * Add error to http log.
     *
     * @param ClientException        $exception
     * @param ServerRequestInterface $request
     */
    public function logError(ClientException $exception, ServerRequestInterface $request)
    {
        $remoteAddress = '-undefined-';
        if (!empty($request->getServerParams()['REMOTE_ADDR'])) {
            $remoteAddress = $request->getServerParams()['REMOTE_ADDR'];
        }

        $this->logger()->warning(
            "{scheme}://{host}{path} caused the error {code} ({message}) by client {remote}.",
            [
                'scheme'  => $request->getUri()->getScheme(),
                'host'    => $request->getUri()->getHost(),
                'path'    => $request->getUri()->getPath(),
                'code'    => $exception->getCode(),
                'message' => $exception->getMessage() ?: '-not specified-',
                'remote'  => $remoteAddress
            ]
        );
    }

    /**
     * Create response for specifier error code, some responses can be have associated view files.
     *
     * @param ClientException        $exception
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function exceptionResponse(
        ClientException $exception,
        ServerRequestInterface $request
    ) {
        if (!empty($request) && $request->getHeaderLine('Accept') == 'application/json') {
            return new JsonResponse(
                ['status' => $exception->getCode()],
                $exception->getCode()
            );
        }

        if (isset($this->config['httpErrors'][$exception->getCode()])) {
            /**
             * Exception response will render html content on demand, it gives us ability to handle
             * response "as exception" somewhere in middleware and do something crazy.
             */
            return new ExceptionResponse(
                $exception,
                $this->viewsProvider()->get($this->config['httpErrors'][$exception->getCode()], [
                    'http'    => $this,
                    'request' => $request
                ])
            );
        }

        return new ExceptionResponse($exception);
    }

    /**
     * Get initial request instance or create new one.
     *
     * @return ServerRequestInterface
     */
    protected function request()
    {
        if (!empty($this->request)) {
            return $this->request;
        }

        //Isolation means that MiddlewarePipeline will handle exception using snapshot and not expose
        //error
        return $this->request = ServerRequestFactory::fromGlobals(
            $_SERVER, $_GET, $_POST, $_COOKIE, $_FILES
        )->withAttribute('basePath', $this->basePath())->withAttribute(
            'isolated', $this->config['isolate']
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function response()
    {
        $response = parent::response();
        foreach ($this->config['headers'] as $header => $value) {
            $response = $response->withHeader($header, $value);
        }

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    protected function endpoint()
    {
        if (!empty($endpoint = parent::endpoint())) {
            //Endpoint specified by user
            return $endpoint;
        }

        return $this->router();
    }

    /**
     * {@inheritdoc}
     */
    protected function createRouter()
    {
        return $this->container->construct($this->config['router']['class'], [
                'routes'   => $this->routes,
                'basePath' => $this->basePath()
            ] + $this->config['router']);
    }

    /**
     * Get associated views component or fetch it from container.
     *
     * @return ViewsInterface
     */
    private function viewsProvider()
    {
        if (!empty($this->views)) {
            return $this->views;
        }

        return $this->views = $this->container->get(ViewsInterface::class);
    }
}
