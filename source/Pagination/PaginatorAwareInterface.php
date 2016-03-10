<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Pagination;

/**
 * Provides ability to associate paginator and execute pagination when needed.
 */
interface PaginatorAwareInterface extends PaginableInterface
{
    /**
     * Manually set paginator instance for specific object.
     *
     * @param PaginatorInterface $paginator
     *
     * @return $this
     */
    public function setPaginator(PaginatorInterface $paginator);

    /**
     * Get paginator for the current selection. Paginate method should be already called.
     *
     * @see paginate()
     *
     * @return PaginatorInterface
     */
    public function paginator();

    /**
     * Indication that object was paginated.
     *
     * @return bool
     */
    public function isPaginated();
}
