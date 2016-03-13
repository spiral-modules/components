<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Pagination;

use Spiral\Pagination\Exceptions\PaginationException;

/**
 * Generic paginator interface with ability to set/get page and limit values.
 */
interface PaginatorInterface
{
    /**
     * Apply paginator to paginable object.
     *
     * @param PaginableInterface $paginable
     * @return PaginableInterface
     * @throws PaginationException
     */
    public function paginate(PaginableInterface $paginable);
}