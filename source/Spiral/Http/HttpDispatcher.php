<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareInterface;
use Spiral\Core\Container\SingletonInterface;
use Spiral\Core\ContainerInterface;
use Spiral\Core\DispatcherInterface;
use Spiral\Core\Traits\SingletonTrait;
use Spiral\Debug\SnapshotInterface;
use Spiral\Debug\Traits\LoggerTrait;
use Spiral\Http\Configs\HttpConfig;
use Spiral\Http\Exceptions\ClientException;
use Spiral\Http\Exceptions\ClientExceptions\ServerErrorException;
use Spiral\Http\Exceptions\HttpException;
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
    use RouterTrait, LoggerTrait, SingletonTrait;

    /**
     * Declares to IoC that component instance should be treated as singleton.
     */
    const SINGLETON = self::class;

    /**
     * @var HttpConfig
     */
    protected $config = null;

    /**
     * Required to render error pages.
     *
     * @var ViewsInterface
     */
    protected $views = null;

    /**
     * @param HttpConfig         $config
     * @param ContainerInterface $container
     */
    public function __construct(HttpConfig $config, ContainerInterface $container)
    {
        $this->config = $config;

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
     * Pass request thought all http middlewares to appropriate endpoint. Default endpoint will be
     * used as fallback. Can thrown an exception happen in internal code.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @param callable               $endpoint User specified endpoint.
     * @return ResponseInterface
     * @throws HttpException
     * @throws \Exception Depends on request isolation.
     */
    public function perform(
        ServerRequestInterface $request,
        ResponseInterface $response = null,
        callable $endpoint = null
    ) {
        try {
            return parent::perform($request, $response, $endpoint);
        } catch (ClientException $exception) {
            //Soft exception (TODO: Pass ResponseInterface)
            return $this->clientException($request, $exception);
        } catch (\Exception $exception) {
            //No isolation, let's throw an exception
            if (!$request->getAttribute('isolated', false)) {
                throw $exception;
            }

            /**
             * @var SnapshotInterface $snapshot
             */
            $snapshot = $this->container->construct(
                SnapshotInterface::class,
                compact('exception')
            );

            //Snapshot must report about itself
            $snapshot->report();

            return $this->handleSnapshot($snapshot, false, $request);
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
     * Handle ClientException.
     *
     * @param ServerRequestInterface $request
     * @param ClientException        $exception
     * @return ResponseInterface
     */
    protected function clientException(ServerRequestInterface $request, ClientException $exception)
    {
        //Logging client error
        $this->logError($exception, $request);

        return $this->exceptionResponse($exception, $request);
    }

    /**
     * Get initial request instance or create new one.
     *
     * @return ServerRequestInterface
     */
    protected function request()
    {
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
                'basePath' => $this->basePath()
            ] + $this->config['router']
        );
    }

    /**
     * Create response for specifier error code, some responses can be have associated view files.
     *
     * @param ClientException        $exception
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    private function exceptionResponse(
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
                $this->viewManager()->get($this->config['httpErrors'][$exception->getCode()], [
                    'http'    => $this,
                    'request' => $request
                ])
            );
        }

        return new ExceptionResponse($exception);
    }

    /**
     * Get associated views component or fetch it from container.
     *
     * @return ViewsInterface
     */
    private function viewManager()
    {
        if (!empty($this->views)) {
            return $this->views;
        }

        return $this->views = $this->container->get(ViewsInterface::class);
    }

    /**
     * Add error to http log.
     *
     * @param ClientException        $exception
     * @param ServerRequestInterface $request
     */
    private function logError(ClientException $exception, ServerRequestInterface $request)
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
}
