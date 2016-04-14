<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Pagination;

/**
 * Paginator with dependecy on count.
 */
interface CountingInterface extends PaginatorInterface
{
    /**
     * Get instance of paginator with a given count. Must not affect existed pagintor.
     *
     * @param string $count
     * @return self
     */
    public function withCount($count);
}