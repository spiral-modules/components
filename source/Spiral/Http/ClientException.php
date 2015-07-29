<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Http;

class ClientException extends \RuntimeException
{
    /**
     * Client exception are treated as "soft error", HttpDispatcher will handle them separate way.
     */
    const BAD_DATA  = 400;
    const NOT_FOUND = 404;
    const ERROR     = 500;

    /**
     * Create ClientException with specified error code and optional message (parameters reverted).
     *
     * @param int|string $code
     * @param string     $message
     */
    public function __construct($code = self::NOT_FOUND, $message = "")
    {
        parent::__construct($message, $code);
    }
}