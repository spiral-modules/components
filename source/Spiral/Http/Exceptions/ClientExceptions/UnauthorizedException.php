<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Http\Exceptions\ClientExceptions;

use Spiral\Http\Exceptions\ClientException;
use Spiral\Http\Response;

/**
 * HTTP 401 exception.
 */
class UnauthorizedException extends ClientException
{
    /**
     * @var int
     */
    protected $code = Response::UNAUTHORIZED;

    /**
     * @param string $message
     */
    public function __construct($message = "")
    {
        parent::__construct($this->code, $message);
    }
}