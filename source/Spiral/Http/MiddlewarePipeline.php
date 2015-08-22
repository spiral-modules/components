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
     * Keep buffered output as part of response.
     *
     * @var bool
     */
    private $keepOutput = false;

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
     * @param bool                             $keepOutput
     */
    public function __construct(
        ContainerInterface $container,
        array $middleware = [],
        $keepOutput = false
    ) {
        $this->container = $container;
        $this->middlewares = $middleware;
        $this->keepOutput = $keepOutput;
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
     * @return ResponseInterface
     */
    public function run(ServerRequestInterface $request)
    {
        return $this->next(0, $request);
    }

    /**
     * Get next chain to be called. Exceptions will be converted to responses.
     *
     * @param int                    $position
     * @param ServerRequestInterface $outerRequest
     * @return callable
     * @throw \Exception
     */
    protected function next($position, ServerRequestInterface $outerRequest)
    {
        $next = function ($request = null) use ($position, $outerRequest) {
            //This function will be provided to next (deeper) middleware
            return $this->next(++$position, $request ?: $outerRequest);
        };

        $response = null;
        try {
            if (!isset($this->middlewares[$position])) {
                //Middleware target endpoint to be called and converted into response
                $response = $this->createResponse($outerRequest);
            } else {
                /**
                 * @var callable $middleware
                 */
                $middleware = $this->middlewares[$position];

                //Middleware specified as class name
                $middleware = is_string($middleware) ? $this->container->construct($middleware) : $middleware;

                //Executing next middleware
                $response = $middleware($outerRequest, $next);
            }
        } catch (\Exception $exception) {
            if (!$outerRequest->getAttribute('isolated', false)) {
                //No isolation
                throw $exception;
            }

            /**
             * @var SnapshotInterface $snapshot
             */
            $snapshot = $this->container->construct(SnapshotInterface::class, compact('exception'));

            //Snapshot must report about itself
            $snapshot->report();

            /**
             * We need HttpDispatcher to convert snapshot into response.
             *
             * @var HttpDispatcher $http
             */
            $http = $this->container->get(HttpDispatcher::class);

            $response = $http->handleSnapshot($snapshot, false, $outerRequest);
        }

        return $response;
    }

    /**
     * Run pipeline target and return generated response.
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    protected function createResponse(ServerRequestInterface $request)
    {
        //Request scope
        $outerRequest = $this->container->replace(ServerRequestInterface::class, $request);

        $outputLevel = ob_get_level();
        ob_start();

        $output = '';
        $response = null;
        try {
            if ($this->target instanceof \Closure) {
                $reflection = new \ReflectionFunction($this->target);
                $response = $reflection->invokeArgs(
                    $this->container->resolveArguments($reflection, ['request' => $request])
                );
            } else {
                //Calling pipeline target
                $response = call_user_func($this->target, $request);
            }
        } catch (ClientException $exception) {
            /**
             * We need HttpDispatcher to get valid error exception.
             *
             * @var HttpDispatcher $http
             */
            $http = $this->container->get(HttpDispatcher::class);

            //Logging error
            $http->logError($exception, $request);
            $response = $http->errorResponse($exception->getCode(), $request);
        } finally {
            while (ob_get_level() > $outputLevel + 1) {
                $output = ob_get_clean() . $output;
            }
        }

        //Closing request scope
        $this->container->restore($outerRequest);
        $output = ob_get_clean() . $output;
        if (!$this->keepOutput) {
            $output = '';
        }

        return $this->wrapResponse($response, $output);
    }

    /**
     * Convert target response into valid instance of ResponseInterface. Can understand string and
     * array/JsonSerializable response values.
     *
     * @param mixed  $response
     * @param string $output Buffer output.
     * @return ResponseInterface
     */
    private function wrapResponse($response, $output = '')
    {
        if ($response instanceof ResponseInterface) {
            if (!empty($output)) {
                $response->getBody()->write($output);
            }

            return $response;
        }

        if (is_array($response) || $response instanceof \JsonSerializable) {
            $code = 200;
            if (is_array($response)) {
                if (!empty($output)) {
                    $response['output'] = $output;
                }

                if (isset($response['status'])) {
                    $code = $response['status'];
                }
            }

            return new JsonResponse($response, $code);
        }

        $psrResponse = new Response();
        $psrResponse->getBody()->write($response . $output);

        return $psrResponse;
    }
}