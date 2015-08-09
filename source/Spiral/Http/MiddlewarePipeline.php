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
     * Pass request and response though every middleware to target and return generated and wrapped response.
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function run(ServerRequestInterface $request)
    {
        return $this->next(0, $request);
    }

    /**
     * Get next chain to be called.
     *
     * @param int                    $position
     * @param ServerRequestInterface $outerRequest
     * @return callable
     */
    protected function next($position, ServerRequestInterface $outerRequest)
    {
        $next = function ($request = null) use ($position, $outerRequest) {
            return $this->next(++$position, $request ?: $outerRequest);
        };

        if (!isset($this->middlewares[$position])) {
            return $this->createResponse($outerRequest);
        }

        /**
         * @var callable $middleware
         */
        $middleware = $this->middlewares[$position];

        //Middleware specified as class name
        $middleware = is_string($middleware) ? $this->container->get($middleware) : $middleware;

        return $middleware($outerRequest, $next);
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

        try {
            if ($this->target instanceof \Closure) {
                $reflection = new \ReflectionFunction($this->target);
                $response = $reflection->invokeArgs(
                    $this->container->resolveArguments($reflection, ['request' => $request])
                );
            } else {
                $response = call_user_func($this->target, $request);
            }
        } finally {
            while (ob_get_level() > $outputLevel + 1) {
                ob_end_clean();
            }
        }

        //Closing request scope
        $this->container->restore($outerRequest);
        $output = ob_get_clean();
        if (!$this->keepOutput) {
            $output = '';
        }

        return $this->wrapResponse($response, $output);
    }

    /**
     * Convert target response into valid instance of ResponseInterface. Can understand string and array/JsonSerializable
     * response values.
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