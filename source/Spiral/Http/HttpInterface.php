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
use Spiral\Http\Exceptions\ClientException;

/**
 * Represent simple http abstraction layer.
 */
interface HttpInterface
{
    /**
     * Get initial request instance or create new one.
     *
     * @return ServerRequestInterface
     */
    public function request();

    /**
     * Execute request using internal http logic.
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws ClientException
     */
    public function perform(ServerRequestInterface $request);

    /**
     * Dispatch response to client.
     *
     * @param ResponseInterface $response
     */
    public function dispatch(ResponseInterface $response);
}