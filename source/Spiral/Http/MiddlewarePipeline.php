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
use Spiral\Core\ContainerInterface;
use Spiral\Debug\SnapshotInterface;
use Spiral\Http\Exceptions\ClientException;
use Spiral\Http\Responses\JsonResponse;

/**
 * Class used to pass request and response thought chain of middlewares.
 */
class MiddlewarePipeline
{
    /**
     * @invisible
     * @var ContainerInterface
     */
    protected $container = null;

    /**
     * Pipeline middlewares.
     *
     * @var callable[]|MiddlewareInterface[]
     */
    protected $middlewares = [];

    /**
     * Endpoint should be called at the deepest level of pipeline.
     *
     * @var callable
     */
    protected $target = null;

    /**
     * @param ContainerInterface               $container
     * @param callable[]|MiddlewareInterface[] $middleware
     */
    public function __construct(ContainerInterface $container, array $middleware = [])
    {
        $this->container = $container;
        $this->middlewares = $middleware;
    }

    /**
     * Register new middleware at the end of chain.
     *
     * @param callable $middleware Can accept middleware class name.
     * @return $this
     */
    public function add($middleware)
    {
        $this->middlewares[] = $middleware;

        return $this;
    }

    /**
     * Set pipeline target.
     *
     * @param callable $target
     * @return $this
     */
    public function target($target)
    {
        $this->target = $target;

        return $this;
    }

    /**
     * Pass request and response though every middleware to target and return generated and wrapped
     * response.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @return ResponseInterface
     */
    public function run(ServerRequestInterface $request, ResponseInterface $response)
    {
        return $this->next(0, $request, $response);
    }

    /**
     * Get next chain to be called. Exceptions will be converted to responses.
     *
     * @param int                    $position
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @return null|ResponseInterface
     * @throws \Exception
     */
    protected function next(
        $position,
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        try {
            if (!isset($this->middlewares[$position])) {
                //Middleware target endpoint to be called and converted into response
                return $this->createResponse($request, $response);
            }

            /**
             * @var callable $middleware
             */
            $middleware = $this->middlewares[$position];
            $middleware = is_string($middleware)
                ? $this->container->construct($middleware)
                : $middleware;

            //Executing next middleware
            return $middleware(
                $request, $response, $this->getNext($position, $request, $response)
            );
        } catch (\Exception $exception) {
            if ($exception instanceof ClientException) {
                //To think about client exception isolation
                return $this->clientException($request, $exception);
            }

            if (!$request->getAttribute('isolated', false)) {
                //No isolation
                throw $exception;
            }

            return $this->errorException($request, $exception);
        }
    }

    /**
     * Run pipeline target and return generated response.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @return ResponseInterface
     */
    protected function createResponse(ServerRequestInterface $request, ResponseInterface $response)
    {
        //Request scope
        $outerRequest = $this->container->replace(ServerRequestInterface::class, $request);
        $outerResponse = $this->container->replace(ResponseInterface::class, $response);

        $outputLevel = ob_get_level();
        $output = '';
        $result = null;

        try {
            ob_start();
            $result = $this->execute($request, $response);
        } finally {
            while (ob_get_level() > $outputLevel + 1) {
                $output = ob_get_clean() . $output;
            }

            //Closing request/response scope
            $this->container->restore($outerRequest);
            $this->container->restore($outerResponse);
        }

        return $this->wrapResponse($response, $result, ob_get_clean() . $output);
    }

    /**
     * Execute endpoint and return it's result.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @return mixed
     */
    protected function execute(ServerRequestInterface $request, ResponseInterface $response)
    {
        if ($this->target instanceof \Closure) {
            $reflection = new \ReflectionFunction($this->target);

            return $reflection->invokeArgs(
                $this->container->resolveArguments($reflection, compact('request', 'response'))
            );
        }

        //Calling pipeline target (do we need reflection here?)
        return call_user_func($this->target, $request, $response);
    }

    /**
     * Handle application exception.
     *
     * @param ServerRequestInterface $request
     * @param \Exception             $exception
     * @return null|ResponseInterface
     */
    protected function errorException(ServerRequestInterface $request, \Exception $exception)
    {
        /**
         * @var SnapshotInterface $snapshot
         */
        $snapshot = $this->container->construct(SnapshotInterface::class, compact('exception'));

        //Snapshot must report about itself
        $snapshot->report();

        /**
         * We need HttpDispatcher to convert snapshot into response.
         */

        return $this->container->get(HttpDispatcher::class)->handleSnapshot(
            $snapshot,
            false,
            $request
        );
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
        /**
         * @var HttpDispatcher $http
         */
        $http = $this->container->get(HttpDispatcher::class);

        //Logging client error
        $http->logError($exception, $request);

        return $http->exceptionResponse($exception, $request);
    }

    /**
     * Convert endpoint result into valid response.
     *
     * @param ResponseInterface $response Initial pipeline response.
     * @param mixed             $result   Generated endpoint output.
     * @param string            $output   Buffer output.
     * @return ResponseInterface
     */
    private function wrapResponse(ResponseInterface $response, $result = null, $output = '')
    {
        if ($result instanceof ResponseInterface) {
            if (!empty($output) && $result->getBody()->isWritable()) {
                $result->getBody()->write($output);
            }

            return $result;
        }

        if (is_array($result) || $result instanceof \JsonSerializable) {
            return $this->writeJson($response, $result, $output, Response::SUCCESS);
        }

        $response->getBody()->write($result . $output);

        return $response;
    }

    /**
     * Get next callable element.
     *
     * @param int                    $position
     * @param ServerRequestInterface $outerRequest
     * @param ResponseInterface      $outerResponse
     * @return \Closure
     */
    private function getNext(
        $position,
        ServerRequestInterface $outerRequest,
        ResponseInterface $outerResponse
    ) {
        $next = function ($request = null, $response = null) use (
            $position,
            $outerRequest,
            $outerResponse
        ) {
            //This function will be provided to next (deeper) middleware
            return $this->next(
                ++$position,
                !empty($request) ? $request : $outerRequest,
                !empty($response) ? $response : $outerResponse
            );
        };

        return $next;
    }


    /**
     * Generate JSON response.
     *
     * @param ResponseInterface $response
     * @param mixed             $result
     * @param string            $output
     * @param int               $code
     * @return JsonResponse
     */
    private function writeJson(
        ResponseInterface $response,
        $result,
        $output,
        $code = Response::SUCCESS
    ) {
        if (is_array($result)) {
            if (!empty($output) && empty($result['output'])) {
                $result['output'] = $output;
            }

            if (isset($result['status'])) {
                $code = $result['status'];
            }
        }

        $response->getBody()->write(json_encode($result));

        return $response->withStatus($code)->withHeader('Content-Type', 'application/json');
    }
}