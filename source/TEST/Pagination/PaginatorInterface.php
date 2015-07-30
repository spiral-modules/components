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

interface PaginatorInterface
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
     * New paginator object is used to create page ranges, in addition to filtering database queries
     * or arrays to select a limited amount of records. By default, it can support ODM and ORM object,
     * DBAL queries and arrays. To add support for Pagination, simply implement pagination interface.
     *
     * @param ServerRequestInterface $request       RequestInterface, will be fetched from Container
     *                                              by "request" alias if not explicitly provided.
     * @param string                 $pageParameter Name of parameter in request query used to store
     *                                              current page number. By default, "page" is used.
     */
    public function __construct(ServerRequestInterface $request, $pageParameter = self::DEFAULT_PARAMETER);

    /**
     * Get current paginator Uri, by default identical to request uri.
     *
     * @return UriInterface
     */
    public function getUri();

    /**
     * Update primary paginator uri. Uri much not include query string.
     *
     * @param UriInterface $uri
     */
    public function setUri(UriInterface $uri);

    /**
     * Update page parameter name from request query. Page number will be fetched from queryParams
     * of provided request instance.
     *
     * @param string|null $pageParameter New page parameter, page number will be fetched again after
     *                                   it is updated.
     * @return self
     */
    public function setParameter($pageParameter);

    /**
     * Get page parameter name, paginator will automatically fetch page number from Request based on
     * this parameter name.
     *
     * @return string
     */
    public function getParameter();

    /**
     * Manually force the page number should be used to filter and limit. Setting the page number
     * after applying paginator to object or array will not return any results. Method will return
     * current page number.
     *
     * @param int $number Page number should be within the range of the highest page (1 - maxPages).
     * @return int
     */
    public function setPage($number = null);

    /**
     * The current page number.
     *
     * @return int
     */
    public function currentPage();

    /**
     * Next page number. The return will be false if the current page is the last page.
     *
     * @return bool|int
     */
    public function nextPage();

    /**
     * Previous page number. The return will be false if the current page is first page.
     *
     * @return bool|int
     */
    public function previousPage();

    /**
     * To specify the amount of records you should use a specified limit. This method will update
     * amount of available pages in paginator.
     *
     * @param int $count Total records count.
     * @return Paginator
     */
    public function setCount($count);

    /**
     * The total count of record should be paginated. This can be set using setCount() method, or
     * automatically by applying paginator to object or array.
     *
     * @return int
     */
    public function count();

    /**
     * The count of pages is needed to represent all records using a specified limit value.
     *
     * @return int
     */
    public function countPages();

    /**
     * The count or records displayed on current page can vary from 0 to any limit value. Only the
     * last page will have less records than is specified in the limit.
     *
     * @return int
     */
    public function countDisplayed();

    /**
     * Amount of records per page.
     *
     * @return int
     */
    public function getLimit();

    /**
     * Specify limit - amount of records per page. This method will update the amount of available
     * pages in paginator.
     *
     * @param int $limit Amount of records per page. Default is 50 records.
     * @return self
     */
    public function setLimit($limit = self::DEFAULT_LIMIT);

    /**
     * The amount of records should be skipped from the start of the paginated sequence.
     *
     * @return int
     */
    public function getOffset();

    /**
     * Does paginator need to be shown? Return false if all records can be shown on one page.
     *
     * @return bool
     */
    public function isRequired();

    /**
     * To apply pagination to a simple array, the following method will update the paginator total
     * records count, regenerate a list of pages and then will return an array fetched with limit and
     * offset from the provided haystack.
     *
     * @param array $haystack Target array must be paginated.
     * @return array
     */
    public function paginateArray(array $haystack);

    /**
     * Apply the paginator to paginable object, the total records count should updated automatically
     * by fetching the value from the target object or it can be manually set without retrieving it by
     * using the count() method. Method will call object limit() and offset() functions. ODM, ORM and
     * DBAL selectors are all supported.
     *
     * @param PaginableInterface $object     Object must be paginable.
     * @param bool               $fetchCount Fetch count from $object->count() method or set manually
     *                                       later.
     * @return PaginableInterface
     */
    public function paginateObject(PaginableInterface $object, $fetchCount = true);

    /**
     * Get the URL associated with a page number. The URL will include page parameter and query string
     * built based on the queryArray.
     *
     * @param int $pageNumber Valid page number.
     * @return string
     */
    public function createURL($pageNumber);
}