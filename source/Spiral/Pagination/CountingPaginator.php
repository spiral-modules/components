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
    public function __construct($limit = 25)
    {
        $this->setLimit($limit);
    }

    /**
     * {@inheritdoc}
     *
     * @return $this
     */
    public function setPage($number)
    {
        $this->pageNumber = abs(intval($number));

        //Real page number
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getPage()
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
    public function setLimit($limit)
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * Get pagination offset.
     *
     * @return int
     */
    public function getOffset()
    {
        return ($this->getPage() - 1) * $this->limit;
    }

    /**
     * {@inheritdoc}
     */
    public function paginate(PaginableInterface $paginable)
    {
        $this->setCount($paginable->count());

        return $paginable->offset($this->getOffset())->limit($this->getLimit());
    }

    /**
     * {@inheritdoc}
     *
     * @return $this
     */
    public function setCount($count)
    {
        $this->count = abs(intval($count));
        if ($this->count > 0) {
            $this->countPages = (int)ceil($this->count / $this->limit);
        } else {
            $this->countPages = 1;
        }

        return $this;
    }

    /**
     * Alias for count.
     *
     * @return int
     */
    public function getCount()
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
    public function countPages()
    {
        return $this->countPages;
    }

    /**
     * {@inheritdoc}
     */
    public function countDisplayed()
    {
        if ($this->getPage() == $this->countPages) {
            return $this->count - $this->getOffset();
        }

        return $this->limit;
    }

    /**
     * {@inheritdoc}
     */
    public function isRequired()
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

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function previousPage()
    {
        if ($this->getPage() > 1) {
            return $this->getPage() - 1;
        }

        return false;
    }
}