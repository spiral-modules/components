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
use Psr\Http\Message\UriInterface;
use Spiral\Pagination\Exceptions\PaginationException;

/**
 * Paginates objects and arrays.
 */
interface PaginatorInterface extends \Countable
{
    /**
     * Default limit value.
     */
    const DEFAULT_LIMIT = 25;

    /**
     * Default page parameter.
     */
    const DEFAULT_PARAMETER = 'page';

    /**
     * @param ServerRequestInterface $request       Source of page number.
     * @param string                 $pageParameter Page parameter from request query data.
     */
    public function __construct(
        ServerRequestInterface $request,
        $pageParameter = self::DEFAULT_PARAMETER
    );

    /**
     * Update associated pagination uri. Uri must not include query string.
     *
     * @param UriInterface $uri
     * @return self
     */
    public function setUri(UriInterface $uri);

    /**
     * Get associated pagination Uri, by default identical to request uri.
     *
     * @return UriInterface
     */
    public function getUri();

    /**
     * Update page parameter name from request query. Page number should be fetched from queryParams
     * of provided request instance.
     *
     * @param string $pageParameter
     * @return self
     */
    public function setParameter($pageParameter);

    /**
     * Get page query parameter name.
     *
     * @return string
     */
    public function getParameter();

    /**
     * Total records to be paginated.
     *
     * @param int $count Total records count.
     * @return Paginator
     */
    public function setCount($count);

    /**
     * Specify limit - amount of records per page. This method will update the amount of available
     * pages in paginator.
     *
     * @param int $limit Amount of records per page.
     * @return self
     */
    public function setLimit($limit);

    /**
     * Amount of records per page.
     *
     * @return int
     */
    public function getLimit();

    /**
     * Set page number.
     *
     * @param int $number
     * @return int
     */
    public function setPage($number);

    /**
     * The amount of records should be skipped from the start of the paginated sequence.
     *
     * @return int
     */
    public function getOffset();

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
     * The current page number.
     *
     * @return int
     */
    public function currentPage();

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
     * Apply paginator to paginable object, should call limit and offset methods and fetch count
     * from object if fetchCount specified as true. Paginated object has to be returned.
     *
     * @param PaginableInterface $object
     * @param bool               $fetchCount
     * @return PaginableInterface
     * @throws PaginationException
     */
    public function paginateObject(PaginableInterface $object, $fetchCount = true);

    /**
     * Create page URL using specific page number. No domain or schema information included by
     * default, starts with path.
     *
     * @param int $pageNumber
     * @return string
     */
    public function createURL($pageNumber);
}