<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\ODM\Entities;

use Psr\Log\LoggerAwareInterface;
use Spiral\Core\Component;
use Spiral\ODM\DocumentEntity;
use Spiral\ODM\MongoManager;
use Spiral\ODM\ODMInterface;
use Spiral\Pagination\Traits\LimitsTrait;
use Spiral\Pagination\Traits\PaginatorTrait;

/**
 * Mocks MongoCollection to aggregate query, limits and sorting values and product DocumentIterator
 * as result.
 *
 * @see  DocumentCursor
 * @link http://docs.mongodb.org/manual/tutorial/query-documents/
 */
class DocumentSelector extends Component implements
    \Countable,
    \IteratorAggregate,
    LoggerAwareInterface,
    \JsonSerializable
{
    use LimitsTrait, PaginatorTrait;

    /**
     * Sort order.
     *
     * @link http://php.net/manual/en/class.mongocollection.php#mongocollection.constants.ascending
     */
    const ASCENDING = 1;

    /**
     * Sort order.
     *
     * @link http://php.net/manual/en/class.mongocollection.php#mongocollection.constants.descending
     */
    const DESCENDING = -1;

    /**
     * @invisible
     *
     * @var ODMInterface
     */
    private $odm = null;

    /**
     * Associated database name or alias.
     *
     * @var string
     */
    private $database = 'default';

    /**
     * Associated MongoCollection name.
     *
     * @var string
     */
    private $collection = null;

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
     * @link http://docs.mongodb.org/manual/tutorial/query-documents/
     *
     * @param ODMInterface $odm
     * @param string       $database   Associated database name/id.
     * @param string       $collection Collection name.
     * @param array        $query      Fields and conditions to query by (initial).
     */
    public function __construct(ODMInterface $odm, $database, $collection, array $query = [])
    {
        $this->odm = $odm;

        $this->name = $collection;
        $this->database = $database;

        $this->query = $query;
    }

    /**
     * @return string
     */
    public function getCollection()
    {
        return $this->collection;
    }

    /**
     * @return string
     */
    public function getDatabase()
    {
        return $this->database;
    }

    /**
     * Set additional query, fields will be merged to currently existed request using array_merge.
     *
     * @link http://docs.mongodb.org/manual/tutorial/query-documents/
     *
     * @param array $query        Fields and conditions to query by.
     * @param bool  $convertDates When true (default) all DateTime objects will be converted into
     *                            MongoDate.
     *
     * @return $this
     */
    public function query(array $query = [], $convertDates = true)
    {
        if ($convertDates) {
            array_walk_recursive($query, function (&$value) {
                if ($value instanceof \DateTime) {
                    //MongoDate is always UTC, which is good :)
                    $value = new \MongoDate($value->getTimestamp());
                }
            });
        }

        $this->query = array_merge($this->query, $query);

        return $this;
    }

    /**
     * Set additional query, fields will be merged to currently existed request using array_merge.
     * Alias for query.
     *
     * @link http://docs.mongodb.org/manual/tutorial/query-documents/
     *
     * @see  query()
     *
     * @param array $query        Fields and conditions to query by.
     * @param bool  $convertDates When true (default) all DateTime objects will be converted into
     *                            MongoDate.
     *
     * @return $this
     */
    public function where(array $query = [], $convertDates = true)
    {
        return $this->query($query, $convertDates);
    }

    /**
     * Set additional query, fields will be merged to currently existed request using array_merge.
     * Alias for query.
     *
     * @link http://docs.mongodb.org/manual/tutorial/query-documents/
     *
     * @see  query()
     *
     * @param array $query        Fields and conditions to query by.
     * @param bool  $convertDates When true (default) all DateTime objects will be converted into
     *                            MongoDate.
     *
     * @return $this
     */
    public function find(array $query = [], $convertDates = true)
    {
        return $this->query($query, $convertDates);
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
     * @return $this
     */
    public function sortBy(array $fields)
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
     * @return $this
     */
    public function orderBy($field, $direction = self::ASCENDING)
    {
        return $this->sortBy($this->sort + [$field => $direction]);
    }

    /**
     * Fetch one record from database using it's primary key. You can use INLOAD and JOIN_ONLY
     * loaders with HAS_MANY or MANY_TO_MANY relations with this method as no limit were used.
     *
     * @see findOne()
     *
     * @param mixed $id Primary key value.
     *
     * @return DocumentEntity|null
     */
    public function findByPK($id)
    {
        return $this->findOne(['_id' => MongoManager::mongoID($id)]);
    }

    /**
     * Select one document or it's fields from collection.
     *
     * @param array $query Fields and conditions to query by.
     *
     * @return DocumentEntity|array
     */
    public function findOne(array $query = [])
    {
        return $this->createCursor($query, [], 1)->getNext();
    }

    /**
     * Count all records matching request.
     *
     * @return int
     */
    public function count()
    {
        return $this->mongoCollection()->count($this->query);
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return $this->getIterator();
    }

    /**
     * {@inheritdoc}
     *
     * @return DocumentCursor|DocumentEntity[]
     */
    public function getIterator()
    {
        return $this->createCursor();
    }

    /**
     * Get instance of DocumentCursor.
     *
     * @return DocumentCursor
     */
    public function getCursor()
    {
        return $this->createCursor();
    }

    /**
     * Destructing.
     */
    public function __destruct()
    {
        $this->odm = $this->paginator = null;
        $this->query = [];
    }

    /**
     * @return array
     */
    public function __debugInfo()
    {
        return [
            'collection' => $this->getDatabase() . '/' . $this->getCollection(),
            'query'      => $this->query,
            'limit'      => $this->getLimit(),
            'offset'     => $this->getOffset(),
            'sort'       => $this->sort,
        ];
    }

    /**
     * Create CursorReader based on stored query, limits and sorting.
     *
     * @param array    $query  Fields and conditions to query by.
     * @param array    $fields Fields of the results to return.
     * @param int|null $limit  Custom limit value.
     *
     * @return DocumentCursor
     *
     * @throws \MongoException
     */
    protected function createCursor($query = [], $fields = [], $limit = null)
    {

    }

    /**
     * {@inheritdoc}
     */
    protected function container()
    {
        if ($this->odm instanceof Component) {
            return $this->odm->container();
        }

        return parent::container();
    }

    /**
     * @return \MongoCollection
     */
    protected function mongoCollection()
    {
        return $this->odm->database($this->database)->selectCollection($this->collection);
    }
}