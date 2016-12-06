<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Pagination;

/**
 * Simple predictable paginator.
 */
class CountingPaginator implements PredictableInterface, \Countable
{
    /**
     * @var int
     */
    private $pageNumber = 1;

    /**
     * @var int
     */
    private $countPages = 1;

    /**
     * Pagination limit.
     *
     * @var int
     */
    private $limit = 25;

    /**
     * @var int
     */
    private $count = 0;

    /**
     * @param int $limit
     */
    public function __construct(int $limit = 25)
    {
        $this->setLimit($limit);
    }

    /**
     * {@inheritdoc}
     *
     * @return $this
     */
    public function setPage(int $number): self
    {
        $this->pageNumber = abs(intval($number));

        //Real page number
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getPage(): int
    {
        if ($this->pageNumber < 1) {
            return 1;
        }

        if ($this->pageNumber > $this->countPages) {
            return $this->countPages;
        }

        return $this->pageNumber;
    }

    /**
     * {@inheritdoc}
     *
     * @return $this
     */
    public function setLimit(int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * {@inheritdoc}
     */
    public function getOffset(): int
    {
        return ($this->getPage() - 1) * $this->limit;
    }

    /**
     * {@inheritdoc}
     */
    public function withCount(int $count): CountingInterface
    {
        $paginator = clone $this;

        return $paginator->setCount($count);
    }

    /**
     * Alias for count.
     *
     * @return int
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * @return int
     */
    public function count()
    {
        return $this->count;
    }

    /**
     * {@inheritdoc}
     */
    public function countPages(): int
    {
        return $this->countPages;
    }

    /**
     * {@inheritdoc}
     */
    public function countDisplayed(): int
    {
        if ($this->getPage() == $this->countPages) {
            return $this->count - $this->getOffset();
        }

        return $this->limit;
    }

    /**
     * {@inheritdoc}
     */
    public function isRequired(): bool
    {
        return ($this->countPages > 1);
    }

    /**
     * {@inheritdoc}
     */
    public function nextPage()
    {
        if ($this->getPage() != $this->countPages) {
            return $this->getPage() + 1;
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function previousPage()
    {
        if ($this->getPage() > 1) {
            return $this->getPage() - 1;
        }

        return null;
    }

    /**
     * Non-Immutable version of withCount.
     *
     * @param int $count
     * @return CountingPaginator
     */
    private function setCount(int $count)
    {
        $this->count = abs(intval($count));
        if ($this->count > 0) {
            $this->countPages = (int)ceil($this->count / $this->limit);
        } else {
            $this->countPages = 1;
        }

        return $this;
    }
}