<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Pagination;

use Psr\Http\Message\ServerRequestInterface;
use Spiral\Pagination\Exceptions\PaginationException;

/**
 * Declares ability to be paginated and store associated paginator.
 */
interface PaginableInterface extends \Countable
{
    /**
     * Set selection limit.
     *
     * @param int $limit
     * @return mixed
     */
    public function limit($limit = 0);

    /**
     * Set selection offset.
     *
     * @param int $offset
     * @return mixed
     */
    public function offset($offset = 0);

    /**
     * Paginate current selection.
     *
     * @param int                    $limit         Pagination limit.
     * @param string                 $pageParameter Name of parameter in request query which is
     *                                              used to store the current page number. "page"
     *                                              by default.
     * @param int                    $count         Forced count value, if 0 paginator will try to
     *                                              fetch count from associated object.
     * @param ServerRequestInterface $request       Has to be specified if no global container set.
     * @return mixed
     * @throws PaginationException
     */
    public function paginate(
        $limit = 50,
        $pageParameter = PaginatorInterface::DEFAULT_PARAMETER,
        $count = 0,
        ServerRequestInterface $request = null
    );

    /**
     * Get paginator for the current selection. Paginate method should be already called.
     *
     * @see paginate()
     * @return Paginator
     * @throws PaginationException
     */
    public function getPaginator();

    /**
     * Indication that object was paginated.
     *
     * @return bool
     */
    public function isPaginated();
}
