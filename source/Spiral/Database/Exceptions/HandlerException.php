<?php
/**
 * Spiral Framework, Core Components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Database\Exceptions;

/**
 * Schema sync related exception.
 */
class HandlerException extends DriverException implements QueryExceptionInterface
{
    /**
     * @param QueryException $e
     */
    public function __construct(QueryException $e)
    {
        parent::__construct($e->getMessage(), $e->getCode(), $e);
    }

    /**
     * {@inheritdoc}
     */
    public function getQuery(): string
    {
        return $this->getPrevious()->getQuery();
    }
}