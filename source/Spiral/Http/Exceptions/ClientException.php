<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Http\Exceptions;

/**
 * Generic client driven http exception.
 */
class ClientException extends HttpException
{
    /**
     * Most common codes.
     */
    const BAD_DATA  = 400;
    const NOT_FOUND = 404;
    const ERROR     = 500;

    /**
     * Code and message positions are reverted.
     *
     * @param int    $code
     * @param string $message
     */
    public function __construct($code = null, $message = "")
    {
        if (empty($code) && empty($this->code)) {
            $code = self::NOT_FOUND;
        }

        parent::__construct($message, $code);
    }
}