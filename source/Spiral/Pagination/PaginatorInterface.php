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
 * Paginates objects and arrays. Theoretically i should create another interface
 * SimplePaginatorInterface without count related methods and extend this interface from simple
 * one. Right now you can simply set count manually, in any scenario it's up to view how to render
 * it.
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
     * @return UriInterface
     */
    public function createUri($pageNumber);
}