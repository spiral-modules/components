<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Pagination;

/**
 * Simple, manually driven and size aware paginator.
 */
class PredictablePaginator implements PredictableInterface
{
    /**
     * @var int
     */
    private $pageNumber = 1;

    /**
     * @var int
     */
    private $countPages = 1;

    /**
     * @var int
     */
    private $limit = 25;

    /**
     * @var int
     */
    private $count = 0;

    /**
     * @param int $limit
     * @param int $count
     */
    public function __construct($limit = 25, $count = 0)
    {
        $this->setLimit($limit);
        $this->setCount($count);
    }
}