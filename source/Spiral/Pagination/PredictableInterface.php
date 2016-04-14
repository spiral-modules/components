<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Pagination;

/**
 * Paginator with predictable length (count).
 */
interface PredictableInterface extends CountingInterface
{
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
     * The count of pages required to represent all records using a specified limit value.
     *
     * @return int
     */
    public function countPages();

    /**
     * The count or records displayed on current page can vary from 0 to any limit value. Only the
     * last page can have less records than is specified in the limit.
     *
     * @return int
     */
    public function countDisplayed();

    /**
     * Does paginator needed to be applied? Should return false if all records can be shown on one
     * page.
     *
     * @return bool
     */
    public function isRequired();

    /**
     * Next page number. Should return will be false if the current page is the last page.
     *
     * @return bool|int
     */
    public function nextPage();

    /**
     * Previous page number. Should return false if the current page is first page.
     *
     * @return bool|int
     */
    public function previousPage();
}
