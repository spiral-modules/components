<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Pagination;

/**
 * Declares ability to be paginated and store associated paginator.
 */
interface PaginableInterface extends \Countable
{
    /**
     * Set selection limit.
     *
     * @param int $limit
     *
     * @return mixed
     */
    public function limit($limit = 0);

    /**
     * @return int
     */
    public function getLimit();

    /**
     * Set selection offset.
     *
     * @param int $offset
     *
     * @return mixed
     */
    public function offset($offset = 0);

    /**
     * @return int
     */
    public function getOffset();
}
