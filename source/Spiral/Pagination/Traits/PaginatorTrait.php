<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Pagination\Traits;

use Interop\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Spiral\Core\Exceptions\SugarException;
use Spiral\Core\FactoryInterface;
use Spiral\Pagination\Exceptions\PaginationException;
use Spiral\Pagination\Paginator;
use Spiral\Pagination\PaginatorInterface;

/**
 * Provides ability to paginate associated instance. Will work with default Paginator or fetch one
 * from container.
 *
 * Compatible with PaginatorAwareInterface.
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
     * Get paginator for the current selection. Paginate method should be already called.
     *
     * @see isPaginated()
     * @see paginate()
     * @return PaginatorInterface|Paginator|null
     */
    public function paginator()
    {
        return $this->paginator;
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
        //Will be used in two places
        $container = $this->container();

        if (empty($request)) {
            if (empty($container) || !$container->has(ServerRequestInterface::class)) {
                throw new SugarException(
                    "Unable to create pagination without specified request."
                );
            }

            //Getting request from container scope
            $request = $container->get(ServerRequestInterface::class);
        }

        if (empty($container) || !$container->has(PaginatorInterface::class)) {
            //Let's use default paginator
            $this->paginator = new Paginator($request, $pageParameter);
        } else {
            //We need constructor
            if ($container instanceof FactoryInterface) {
                $constructor = $container;
            } else {
                $constructor = $container->get(FactoryInterface::class);
            }

            //We can also create paginator using container
            $this->paginator = $constructor->make(PaginatorInterface::class, compact(
                'request', 'pageParameter'
            ));
        }

        $this->paginator->setLimit($limit);

        return $this;
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

        return $this->paginator->paginate($this);
    }

    /**
     * @return ContainerInterface
     */
    abstract protected function container();
}