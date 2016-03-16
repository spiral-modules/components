<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Pagination;

/**
 * Responsible for paginator creation based on a given pagination parameter.
 */
interface PaginatorsInterface
{
    /**
     * Create paginator for a given parameter, scope request must be resolved automatically.
     *
     * @param string $parameter
     * @param int    $limit Pagination limit
     * @return PaginatorInterface
     */
    public function createPaginator($parameter, $limit = 25);
}