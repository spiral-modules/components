<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Pagination;

use Psr\Http\Message\UriInterface;
use Spiral\Pagination\Exceptions\PaginationException;

/**
 * Paginates objects and arrays. Theoretically i should create another interface
 * SimplePaginatorInterface without count related methods and extend this interface from simple
 * one. Right now you can simply set count manually, in any scenario it's up to view how to render
 * it.
 */
interface PaginatorInterface
{
    /**
     * Set page number.
     *
     * @param int $number
     * @return int
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
     * @return $this
     */
    public function setLimit($limit);

    /**
     * Get pagination limit (items per page).
     *
     * @return int
     */
    public function getLimit();

    /**
     * Set initial paginator uri
     *
     * @internal to be moved to UriPaginator
     * @param UriInterface $uri
     */
    //public function setUri(UriInterface $uri);

    /**
     * Create page URL using specific page number. No domain or schema information included by
     * default, starts with path.
     *
     * @internal to be moved to UriPaginator
     * @param int $pageNumber
     * @return UriInterface
     */
    //public function uri($pageNumber);

    /**
     * Apply paginator to paginable object.
     *
     * @param PaginableInterface $paginable
     * @return PaginableInterface
     * @throws PaginationException
     */
    public function paginate(PaginableInterface $paginable);
}
