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
use Spiral\Pagination\Paginator;
use Spiral\Pagination\PaginatorInterface;

/**
 * Provides ability to paginate associated instance. Will work with default Paginator or fetch one
 * from container.
 */
trait PaginatorTrait
{
    /**
     * @var int
     */
    protected $limit = 0;

    /**
     * @var int
     */
    protected $offset = 0;

    /**
     * @var Paginator
     */
    protected $paginator = null;

    /**
     * Forced pagination count. If 0 PaginatorTrait will try to fetch value from associated object
     * (this).
     *
     * @var int
     */
    protected $paginationCount = 0;

    /**
     * @return ContainerInterface
     */
    abstract public function container();

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
     * Paginate current selection.
     *
     * @param int                    $limit         Pagination limit.
     * @param string                 $pageParameter Name of parameter in request query which is used to
     *                                              store the current page number. "page" by default.
     * @param int                    $count         Forced count value, if 0 paginator will try to fetch
     *                                              count from associated object.
     * @param ServerRequestInterface $request       Has to be specified if no global container set.
     * @return $this
     * @throws PaginationException
     */
    public function paginate(
        $limit = PaginatorInterface::DEFAULT_LIMIT,
        $count = null,
        $pageParameter = PaginatorInterface::DEFAULT_PARAMETER,
        ServerRequestInterface $request = null
    )
    {
        if (empty($container = $this->container()) && empty($request))
        {
            throw new PaginationException("Unable to create pagination without specified request.");
        }

        //If no request provided we can try to fetch it from container
        $request = !empty($request) ? $request : $container->get(ServerRequestInterface::class);

        if (empty($container) || !$container->hasBinding(PaginatorInterface::class))
        {
            //Let's use default paginator
            $this->paginator = new Paginator($request, $pageParameter);
        }
        else
        {
            $this->paginator = $container->get(PaginatorInterface::class, compact(
                'request', 'pageParameter'
            ));
        }

        $this->paginator->setLimit($limit);
        $this->paginationCount = $count;

        return $this;
    }

    /**
     * Get paginator for the current selection. Paginate method should be already called.
     *
     * @see paginate()
     * @return Paginator
     * @throws PaginationException
     */
    public function getPaginator()
    {
        if (!$this->paginator)
        {
            throw new PaginationException(
                "Selection has to be paginated before requesting Paginator."
            );
        }

        return $this->paginator;
    }

    /**
     * Apply pagination to current object. Will be applied only if internal paginator already constructed.
     *
     * @return $this
     */
    protected function runPagination()
    {
        if (empty($this->paginator))
        {
            return $this;
        }

        if (!empty($this->paginationCount))
        {
            $this->paginator->setCount($this->paginationCount);
        }

        return $this->paginator->paginateObject($this, empty($this->paginationCount));
    }
}