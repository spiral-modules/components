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
use Spiral\Debug\Traits\LoggerTrait;
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
    use LimitsTrait, PaginatorTrait, LoggerTrait;

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
     * Associated ODM class.
     *
     * @var string
     */
    private $class = '';

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
     * @param string       $class Associated document class.
     * @param array        $query Fields and conditions to query by (initial).
     */
    public function __construct(ODMInterface $odm, $class, array $query = [])
    {
        $this->odm = $odm;
        $this->class = $class;
        $this->query = $query;
    }

    /**
     * @return string
     */
    public function getCollection()
    {
        return $this->odm->schema($this->class, ODMInterface::D_DB);
    }

    /**
     * @return string
     */
    public function getDatabase()
    {
        return $this->odm->schema($this->class, ODMInterface::D_COLLECTION);
    }

    /**
     * Set additional query, fields will be merged to currently existed request using array_merge.
     *
     * @link http://docs.mongodb.org/manual/tutorial/query-documents/
     *
     * @param array $query          Fields and conditions to query by.
     * @param bool  $normalizeDates When true (default) all DateTime objects will be converted into
     *                              MongoDate.
     *
     * @return $this
     */
    public function query(array $query = [], $normalizeDates = true)
    {
        if ($normalizeDates) {
            $query = $this->normalizeDates($query);
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
     * @param array $query          Fields and conditions to query by.
     * @param bool  $normalizeDates When true (default) all DateTime objects will be converted into
     *                              MongoDate.
     *
     * @return $this
     */
    public function where(array $query = [], $normalizeDates = true)
    {
        return $this->query($query, $normalizeDates);
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
     * @return $this
     */
    public function find(array $query = [], $normalizeDates = true)
    {
        return $this->query($query, $normalizeDates);
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
     * @param array $query          Fields and conditions to query by. Query will not be added to an
     *                              existed query array.
     * @param bool  $normalizeDates When true (default) all DateTime objects will be converted into
     *                              MongoDate.
     *
     * @return DocumentEntity|array
     */
    public function findOne(array $query = [], $normalizeDates = true)
    {
        if ($normalizeDates) {
            $query = $this->normalizeDates($query);
        }

        return $this->createCursor($query, [], 1)->current();
    }

    /**
     * Fetch all available document instances from query.
     *
     * @return DocumentEntity[]
     */
    public function fetchDocuments()
    {
        $result = [];
        foreach ($this->createCursor() as $document) {
            $result[] = $document;
        }

        return $result;
    }

    /**
     * Alias for fetchDocuments().
     *
     * @return DocumentEntity[]
     */
    public function all()
    {
        return $this->fetchDocuments();
    }

    /**
     * Fetch all available documents as arrays of fields.
     *
     * @param array $fields Fields of the results to return.
     *
     * @return array
     */
    public function fetchFields($fields = [])
    {
        $result = [];
        foreach ($this->createCursor([], $fields) as $document) {
            $result[] = $document;
        }

        return $result;
    }

    /**
     * Count all records matching request inside given limit/offset window.
     *
     * @return int
     */
    public function count()
    {
        return $this->mongoCollection()->count($this->query, [
            'limit'  => $this->limit,
            'offset' => $this->offset
        ]);
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
        //We have to create original mongo cursor first
        $cursor = $this->mongoCollection()->find(array_merge($this->query, $query));

        //Getting selection specific paginator
        if ($this->hasPaginator()) {
            $paginator = $this->configurePaginator($this->count());

            //We have to ensure that selection works inside given pagination window
            $cursor->limit(min($this->getLimit(), $paginator->getLimit()));

            //Making sure that window is shifted
            $cursor->skip($this->getOffset() + $paginator->getOffset());
        } else {
            $cursor->limit($this->getLimit())->skip($this->getOffset());
        }

        if (!empty($this->sort)) {
            $cursor->sort($this->sort);
        }

        //Profiling and logging
        $this->describeCursor(
            $cursor,
            $this->mongoDatabase()->getProfiling()
        );

        //Now we need our cursor wrapper instance
        $iterator = new DocumentCursor($cursor, $this->class, $this->odm);

        if (!empty($fields)) {
            //Iteration over fields array
            $iterator->fields($fields);
        }

        return $iterator;
    }

    /**
     * {@inheritdoc}
     */
    protected function iocContainer()
    {
        if ($this->odm instanceof Component) {
            return $this->odm->iocContainer();
        }

        return parent::iocContainer();
    }

    /**
     * @return \MongoCollection
     */
    private function mongoCollection()
    {
        return $this->mongoDatabase()->selectCollection($this->getCollection());
    }

    /**
     * @return MongoDatabase
     */
    private function mongoDatabase()
    {
        return $this->odm->database($this->getDatabase());
    }

    /**
     * Converts DateTime objects into MongoDatetime.
     *
     * @param array $query
     * @return array
     */
    private function normalizeDates(array $query)
    {
        array_walk_recursive($query, function (&$value) {
            if ($value instanceof \DateTime) {
                //MongoDate is always UTC, which is good :)
                $value = new \MongoDate($value->getTimestamp());
            }
        });

        return $query;
    }

    /**
     * Debug information and logging.
     *
     * @param \MongoCursor $cursor
     * @param int|bool     $profiling Profiling level
     */
    protected function describeCursor(
        \MongoCursor $cursor,
        $profiling = MongoDatabase::PROFILE_DISABLED
    ) {
        if ($profiling == MongoDatabase::PROFILE_DISABLED) {
            return;
        }

        if ((!empty($this->limit) || !empty($this->offset)) && empty($this->sort)) {

            //Document can travel in mongo collection
            $this->logger()->warning(
                'MongoDB query executed with limit/offset but without specified sorting.'
            );
        }

        $debug = [
            'query' => $this->query,
            'sort'  => $this->sort
        ];

        if (!empty($this->limit)) {
            $debug['limit'] = !empty($limit) ? (int)$limit : (int)$this->limit;
        }

        if (!empty($this->offset)) {
            $debug['offset'] = (int)$this->offset;
        }

        if ($profiling == MongoDatabase::PROFILE_EXPLAIN) {
            $debug['explained'] = $cursor->explain();
        }

        $this->logger()->debug('{db}/{collection}: ' . json_encode($debug, JSON_PRETTY_PRINT), [
            'db'         => $this->getDatabase(),
            'collection' => $this->getCollection(),
            'queryInfo'  => $debug,
        ]);
    }
}