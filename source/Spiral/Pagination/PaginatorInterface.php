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
     * Set page number.
     *
     * @param int $number
     * @return int Normalized page number.
     */
    public function setPage($number);

    /**
     * Get current page number.
     *
     * @return int
     */
    public function getPage();

    /**
     * Set pagination limit.
     *
     * @param int $limit
     * @return int
     */
    public function setLimit($limit);

    /**
     * Get pagination limit.
     *
     * @return int
     */
    public function getLimit();

    /**
     * Apply paginator to paginable object.
     *
     * @param PaginableInterface $paginable
     * @return PaginableInterface
     * @throws PaginationException
     */
    public function paginate(PaginableInterface $paginable);
}
