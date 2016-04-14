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
use Spiral\Pagination\CountingInterface;
use Spiral\Pagination\Exceptions\PaginationException;
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
     * Indication that object was paginated.
     *
     * @return bool
     */
    public function hasPaginator()
    {
        return !empty($this->paginator);
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
     * @see hasPaginator()
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
                'Unable to create paginator, PaginatorsInterface binding is missing or container is set'
            );
        }

        $this->paginator = $container->get(PaginatorsInterface::class)->createPaginator(
            $parameter,
            $limit
        );

        return $this;
    }

    /**
     * Get paginator instance configured for a given count. Must not affect already associated
     * paginator instance.
     *
     * @param int|null $count Can be skipped.
     *
     * @return PaginatorInterface
     */
    protected function configurePaginator($count = null)
    {
        $paginator = $this->getPaginator();

        if (!empty($count) && $paginator instanceof CountingInterface) {
            $paginator = $paginator->withCount($count);
        } else {
            $paginator = clone $paginator;
        }

        return $paginator;
    }

    /**
     * @return ContainerInterface
     */
    abstract protected function container();
}
