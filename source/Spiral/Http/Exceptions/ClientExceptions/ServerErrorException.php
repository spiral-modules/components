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
 * HTTP 500 exception.
 */
class ServerErrorException extends ClientException
{
    protected $code = Response::SERVER_ERROR;

}

