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
     * Apply pagination to a simple array, should fetch count from target array and return sliced
     * array version.
     *
     * @param array $haystack Target array must be paginated.
     * @return array
     * @throws PaginationException
     */
    public function paginateArray(array $haystack);

    /**
     * Apply paginator to paginable object.
     *
     * @param PaginableInterface $object
     * @return PaginableInterface
     * @throws PaginationException
     */
    public function paginateObject(PaginableInterface $object);

    /**
     * Create page URL using specific page number. No domain or schema information included by
     * default, starts with path.
     *
     * @param int $pageNumber
     * @return UriInterface
     */
    public function createUri($pageNumber);
}