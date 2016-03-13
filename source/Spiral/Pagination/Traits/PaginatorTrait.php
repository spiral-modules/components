<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Pagination\Traits;

use Interop\Container\ContainerInterface;
use Spiral\Core\Exceptions\SugarException;
use Spiral\Pagination\Exceptions\PaginationException;
use Spiral\Pagination\Paginator;
use Spiral\Pagination\PaginatorInterface;
use Spiral\Pagination\PaginatorsInterface;

/**
 * Provides ability to paginate associated instance. Will work with default Paginator or fetch one
 * from container.
 *
 * Compatible with PaginatorAwareInterface.
 */
trait PaginatorTrait
{
    /**
     * @internal
     *
     * @var PaginatorInterface|null
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
     *
     * @return int
     */
    abstract public function count();

    /**
     * Set selection limit.
     *
     * @param int $limit
     *
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
     *
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
     *
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
     *
     * @return PaginatorInterface
     */
    public function getPaginator()
    {
        if (empty($this->paginator)) {
            throw new PaginationException("Unable to get paginator, no paginator were set");
        }

        return $this->paginator;
    }

    /**
     * Alias for getPaginator. Deprecated since paginator can not be created automatically on
     * request so getPaginator call is more obvious.
     *
     * @deprecated Use getPaginator() instead.
     * @return PaginatorInterface
     */
    public function paginator()
    {
        return $this->getPaginator();
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
     * @param int    $limit     Pagination limit.
     * @param string $parameter Name of parameter to associate paginator with, by default query
     *                          parameter of active request to be used.
     * @return $this
     *
     * @throws SugarException
     */
    public function paginate($limit = 25, $parameter = 'page')
    {
        $container = $this->container();

        if (empty($container) || !$container->has(PaginatorsInterface::class)) {
            throw new SugarException(
                'Unable to create paginator, PaginatorsInterface binding is missing or container is set.'
            );
        }

        $this->paginator = $container->get(PaginatorsInterface::class)->getPaginator(
            $parameter,
            $limit
        );

        return $this;
    }

    /**
     * Apply pagination to current object. Will be applied only if internal paginator already
     * constructed.
     *
     * @return $this
     *
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
