<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Pagination\Traits;

use Psr\Http\Message\ServerRequestInterface;
use Spiral\Core\ContainerInterface;
use Spiral\Pagination\Exceptions\PaginationException;
use Spiral\Pagination\PaginableInterface;
use Spiral\Pagination\Paginator;
use Spiral\Pagination\PaginatorInterface;

/**
 * Provides ability to paginate associated instance. Will work with default Paginator or fetch one
 * from container.
 */
trait PaginatorTrait
{
    /**
     * @var PaginatorInterface
     */
    private $paginator = null;

    /**
     * @var int
     */
    protected $limit = 0;

    /**
     * @var int
     */
    protected $offset = 0;

    /**
     * Count elements of an object.
     *
     * @link http://php.net/manual/en/countable.count.php
     * @return int
     */
    abstract public function count();

    /**
     * Set selection limit.
     *
     * @param int $limit
     * @return mixed
     */
    public function limit($limit = 0)
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * @return int
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * Set selection offset.
     *
     * @param int $offset
     * @return mixed
     */
    public function offset($offset = 0)
    {
        $this->offset = $offset;

        return $this;
    }

    /**
     * @return int
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * Paginate current selection using Paginator class.
     *
     * @param int                    $limit         Pagination limit.
     * @param string                 $pageParameter Name of parameter in request query which is
     *                                              used to store the current page number. "page"
     *                                              by default.
     * @param ServerRequestInterface $request       Has to be specified if no global container set.
     * @return $this
     * @throws PaginationException
     */
    public function paginate(
        $limit = Paginator::DEFAULT_LIMIT,
        $pageParameter = Paginator::DEFAULT_PARAMETER,
        ServerRequestInterface $request = null
    ) {
        if (empty($container = $this->container()) && empty($request)) {
            throw new PaginationException("Unable to create pagination without specified request.");
        }

        //If no request provided we can try to fetch it from container
        $request = !empty($request) ? $request : $container->get(ServerRequestInterface::class);

        if (empty($container) || !$container->has(PaginatorInterface::class)) {
            //Let's use default paginator
            $this->paginator = new Paginator($request, $pageParameter);
        } else {
            //We can also create paginator using container
            $this->paginator = $container->construct(Paginator::class, compact(
                'request', 'pageParameter'
            ));
        }

        $this->paginator->setLimit($limit);

        return $this;
    }

    /**
     * Manually set paginator instance for specific object.
     *
     * @param PaginatorInterface $paginator
     * @return $this
     */
    public function setPaginator(PaginatorInterface $paginator)
    {
        $this->paginator = $paginator;

        return $this;
    }

    /**
     * Indication that object was paginated.
     *
     * @return bool
     */
    public function isPaginated()
    {
        return !empty($this->paginator);
    }

    /**
     * Get paginator for the current selection. Paginate method should be already called.
     *
     * @see isPaginated()
     * @see paginate()
     * @return PaginatorInterface|Paginator|null
     */
    public function getPaginator()
    {
        return $this->paginator;
    }

    /**
     * Apply pagination to current object. Will be applied only if internal paginator already
     * constructed.
     *
     * @return $this
     * @throws PaginationException
     */
    protected function applyPagination()
    {
        if (empty($this->paginator)) {
            return $this;
        }

        if ($this->paginator instanceof PaginatorInterface && $this instanceof PaginableInterface) {
            return $this->paginator->paginateObject($this);
        }

        throw new PaginationException(
            "Unable to paginate, PaginableInterface not implemented."
        );
    }

    /**
     * @return ContainerInterface
     */
    abstract protected function container();
}