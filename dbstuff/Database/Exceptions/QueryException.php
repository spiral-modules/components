<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Exceptions;

/**
 * Query specific exception (bad parameters, database failure).
 *
 * @todo change hierarchy?
 * @todo add ConstrainException
 */
class QueryException extends DatabaseException
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
    public function pdoException()
    {
        return $this->getPrevious();
    }
}
