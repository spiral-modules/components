<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Exceptions;

use Spiral\Core\Exceptions\RuntimeException;

/**
 * Query specific exception (bad parameters, database failure).
 */
class QueryException extends RuntimeException
{
    /**
     * {@inheritdoc}
     *
     * @param \PDOException $exception
     */
    public function __construct(\PDOException $exception)
    {
        parent::__construct($exception->getMessage(), (int)$exception->getCode(), $exception);
    }

    /**
     * @return \PDOException
     */
    public function getPDOException()
    {
        return $this->getPrevious();
    }
}