<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ODM\Entities;

use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use Spiral\Core\Component;
use Spiral\ODM\CompositableInterface;
use Spiral\ODM\ODMInterface;
use Spiral\Pagination\PaginatorAwareInterface;
use Spiral\Pagination\Traits\LimitsTrait;
use Spiral\Pagination\Traits\PaginatorTrait;

class DocumentSelector extends Component implements \Countable, PaginatorAwareInterface
{
    use LimitsTrait, PaginatorTrait;

    /**
     * Sort orders.
     */
    const ASCENDING  = 1;
    const DESCENDING = -1;

    /**
     * @var Collection
     */
    private $collection;

    /**
     * Document class being selected.
     *
     * @var string
     */
    private $class;

    /**
     * @var ODMInterface
     */
    private $odm;

    /**
     * Fields and conditions to query by.
     *
     * @link http://docs.mongodb.org/manual/tutorial/query-documents/
     *
     * @var array
     */
    private $query = [];

    /**
     * Fields to sort.
     *
     * @var array
     */
    private $sort = [];

    /**
     * @param Collection   $collection
     * @param string       $class
     * @param ODMInterface $odm
     */
    public function __construct(Collection $collection, string $class, ODMInterface $odm)
    {
        $this->collection = $collection;
        $this->class = $class;
        $this->odm = $odm;
    }

    /**
     * Set additional query, fields will be merged to currently existed request using array_merge.
     * Alias for query.
     *
     * @link http://docs.mongodb.org/manual/tutorial/query-documents/
     *
     * @see  query()
     *
     * @param array $query          Fields and conditions to query by.
     * @param bool  $normalizeDates When true (default) all DateTime objects will be converted into
     *                              MongoDate.
     *
     * @return self|$this
     */
    public function find(array $query = [], bool $normalizeDates = true): DocumentSelector
    {
        return $this->where($query, $normalizeDates);
    }

    /**
     * Set additional query, fields will be merged to currently existed request using array_merge.
     * Alias for query.
     *
     * @link http://docs.mongodb.org/manual/tutorial/query-documents/
     *
     * @see  query()
     *
     * @param array $query          Fields and conditions to query by.
     * @param bool  $normalizeDates When true (default) all DateTime objects will be converted into
     *                              MongoDate.
     *
     * @return self|$this
     */
    public function where(array $query = [], bool $normalizeDates = true): DocumentSelector
    {
        if ($normalizeDates) {
            $query = $this->normalizeDates($query);
        }

        $this->query = array_merge($this->query, $query);

        return $this;
    }

    /**
     * Sorts the results by given fields.
     *
     * @link http://www.php.net/manual/en/mongocursor.sort.php
     *
     * @param array $fields An array of fields by which to sort. Each element in the array has as
     *                      key the field name, and as value either 1 for ascending sort, or -1 for
     *                      descending sort.
     *
     * @return self|$this
     */
    public function sortBy(array $fields): DocumentSelector
    {
        $this->sort = $fields;

        return $this;
    }

    /**
     * Alias for sortBy.
     *
     * @param string $field
     * @param int    $direction
     *
     * @return self|$this
     */
    public function orderBy(string $field, int $direction = self::ASCENDING): DocumentSelector
    {
        return $this->sortBy($this->sort + [$field => $direction]);
    }

    /**
     * Select one document or it's fields from collection.
     *
     * @param array $query          Fields and conditions to query by. Query will not be added to an
     *                              existed query array.
     * @param bool  $normalizeDates When true (default) all DateTime objects will be converted into
     *                              MongoDate.
     *
     * @return CompositableInterface|null
     */
    public function findOne(array $query = [], bool $normalizeDates = true)
    {
        if ($normalizeDates) {
            $query = $this->normalizeDates($query);
        }

        $result = $this->collection->findOne(array_merge($this->query, $query), []);

        //todo: process result
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return $this->getIterator();
    }

    public function getIterator()
    {
    }

    /**
     * Count collection. Attention, this method depends on current values set for limit and offset!
     *
     * @return int
     */
    public function count(): int
    {
        //Create options?
        return $this->collection->count($this->query, [
            'skip'  => $this->offset,
            'limit' => $this->limit
        ]);
    }

    /**
     * @return array
     */
    public function __debugInfo()
    {
        return [
            'database'   => $this->collection->getDatabaseName(),
            'collection' => $this->collection->getCollectionName(),
            'query'      => $this->query,
            'limit'      => $this->limit,
            'offset'     => $this->offset,
            'sort'       => $this->sort
        ];
    }

    /**
     * Destructing.
     */
    public function __destruct()
    {
        $this->collection = null;
        $this->odm = null;
        $this->paginator = null;
        $this->query = [];
        $this->sort = null;
    }

    protected function createCursor(array $query)
    {

    }

    /**
     * Options to be send to find() method of MongoDB\Collection. Options are based on how selector
     * was configured.
     *
     * @return array
     */
    protected function createOptions(): array
    {
        return [
            'skip' => $this->offset,
            'limit'  => $this->limit,
            'sort'   => $this->sort
        ];
    }

    /**
     * Converts DateTime objects into MongoDatetime.
     *
     * @param array $query
     *
     * @return array
     */
    protected function normalizeDates(array $query): array
    {
        array_walk_recursive($query, function (&$value) {
            if ($value instanceof \DateTime) {
                //MongoDate is always UTC, which is good :)
                $value = new UTCDateTime($value);
            }
        });

        return $query;
    }

    /**
     * @return \Interop\Container\ContainerInterface|null
     */
    protected function iocContainer()
    {
        if ($this->odm instanceof Component) {
            //Forwarding container scope
            return $this->odm->iocContainer();
        }

        return parent::iocContainer();
    }
}