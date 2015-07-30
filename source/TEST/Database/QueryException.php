<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database;

use Spiral\Core\ExceptionInterface;

class QueryException extends \PDOException implements ExceptionInterface
{
    /**
     * Convert PDOException.
     *
     * @param \PDOException $exception
     * @return static
     */
    public static function createFromPDO(\PDOException $exception)
    {
        $instance = new static();
        $instance->code = $exception->code;
        $instance->message = $exception->message;
        $instance->file = $exception->file;
        $instance->line = $exception->line;
        $instance->errorInfo = $exception->errorInfo;

        return $instance;
    }
}