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
 * @todo add more sub exceptions
 */
class QueryException extends DBALException
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
