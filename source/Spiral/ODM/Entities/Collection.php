<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ODM\Entities;

use Psr\Log\LoggerAwareInterface;
use Spiral\Core\Component;
use Spiral\Debug\Traits\LoggerTrait;
use Spiral\ODM\Collection\DocumentIterator;
use Spiral\ODM\Document;
use Spiral\ODM\ODM;
use Spiral\Pagination\PaginableInterface;
use Spiral\Pagination\Traits\PaginatorTrait;

/**
 * Mocks MongoCollection to aggregate query, limits and sorting values and product DocumentIterator as result.
 *
 * @see  DocumentIterator
 * @link http://docs.mongodb.org/manual/tutorial/query-documents/
 *
 * Set of MongoCollection mocked methods:
 * @method bool getSlaveOkay()
 * @method bool setSlaveOkay($slave_okay)
 * @method array getReadPreference()
 * @method bool setReadPreference($read_preference, $tags)
 * @method array drop()
 * @method array validate($validate)
 * @method bool|array insert($array_of_fields_OR_object, $options = [])
 * @method mixed batchInsert($documents, $options = [])
 * @method bool update($old_array_of_fields_OR_object, $new_array_of_fields_OR_object, $options = [])
 * @method bool|array remove($array_of_fields_OR_object, $options = [])
 * @method bool ensureIndex($key_OR_array_of_keys, $options = [])
 * @method array deleteIndex($string_OR_array_of_keys)
 * @method array deleteIndexes()
 * @method array getIndexInfo()
 * @method save($array_of_fields_OR_object, $options = [])
 * @method array createDBRef($array_with_id_fields_OR_MongoID)
 * @method array getDBRef($reference)
 * @method array group($keys_or_MongoCode, $initial_value, $array_OR_MongoCode, $options = [])
 * @method bool|array distinct($key, $query)
 * @method array aggregate(array $pipeline, array $op, array $pipelineOperators)
 */
class Collection extends Component implements \Countable, \IteratorAggregate, PaginableInterface, LoggerAwareInterface
{
    /**
     * Collection queries can be paginated, in addition profiling messages will be dumped into log.
     */
    use LoggerTrait, PaginatorTrait;

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
     * @var string
     */
    private $name = '';

    /**
     * @var string
     */
    private $database = 'default';

    /**
     * Associated MongoCollection.
     *
     * @var \MongoCollection
     */
    private $collection = null;

    /**
     * Fields and conditions to query by.
     *
     * @link http://docs.mongodb.org/manual/tutorial/query-documents/
     * @var array
     */
    protected $query = [];

    /**
     * Fields to sort.
     *
     * @var array
     */
    protected $sort = [];

    /**
     * @invisible
     * @var ODM
     */
    protected $odm = null;

    /**
     * @link http://docs.mongodb.org/manual/tutorial/query-documents/
     * @param ODM    $odm        ODMManager component instance.
     * @param string $database   Associated database name/id.
     * @param string $collection Collection name.
     * @param array  $query      Fields and conditions to query by.
     */
    public function __construct(ODM $odm, $database, $collection, array $query = [])
    {
        $this->odm = $odm;
        $this->name = $collection;
        $this->database = $database;
        $this->query = $query;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
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
     * @param array $query Fields and conditions to query by.
     * @return $this
     */
    public function query(array $query = [])
    {
        array_walk_recursive($query, function (&$value) {
            if ($value instanceof \DateTime) {
                //MongoDate is always UTC, which is good :)
                $value = new \MongoDate($value->getTimestamp());
            }
        });

        $this->query = array_merge($this->query, $query);

        return $this;
    }

    /**
     * Set additional query field, fields will be merged to currently existed request using array_merge. Alias for query.
     *
     * @link http://docs.mongodb.org/manual/tutorial/query-documents/
     * @param array $query Fields and conditions to query by.
     * @return $this
     */
    public function where(array $query = [])
    {
        return $this->query($query);
    }

    /**
     * Set additional query field, fields will be merged to currently existed request using array_merge. Alias for query.
     *
     * @link http://docs.mongodb.org/manual/tutorial/query-documents/
     * @param array $query Fields and conditions to query by.
     * @return $this
     */
    public function find(array $query = [])
    {
        return $this->query($query);
    }

    /**
     * Current fields and conditions to query by.
     *
     * @link http://docs.mongodb.org/manual/tutorial/query-documents/
     * @return array
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Sorts the results by given fields.
     *
     * @link http://www.php.net/manual/en/mongocursor.sort.php
     * @param array $fields An array of fields by which to sort. Each element in the array has as
     *                      key the field name, and as value either 1 for ascending sort, or -1 for
     *                      descending sort.
     * @return $this
     */
    public function sortBy(array $fields)
    {
        $this->sort = $fields;

        return $this;
    }

    /**
     * Select one document or it's fields from collection.
     *
     * @param array $query Fields and conditions to query by.
     * @return Document|array
     */
    public function findOne(array $query = [])
    {
        return $this->createCursor($query, [], 1)->getNext();
    }

    /**
     * Fetch all available document instances from query.
     *
     * @return Document[]
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
     * Fetch all available documents as arrays of fields.
     *
     * @param array $fields Fields of the results to return.
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
     * {@inheritdoc}
     */
    public function count()
    {
        return $this->mongoCollection()->count($this->query);
    }

    /**
     * {@inheritdoc}
     *
     * @return DocumentIterator|Document[]
     */
    public function getIterator()
    {
        return $this->createCursor();
    }

    /**
     * Bypass call to MongoCollection.
     *
     * @param string $method    Method name.
     * @param array  $arguments Method arguments.
     * @return mixed
     */
    public function __call($method, array $arguments = [])
    {
        return call_user_func_array([$this->mongoCollection(), $method], $arguments);
    }

    /**
     * Destructing.
     */
    public function __destruct()
    {
        $this->odm = $this->collection = $this->paginator = null;
        $this->query = [];
    }

    /**
     * @return Object
     */
    public function __debugInfo()
    {
        return (object)[
            'collection' => $this->database . '/' . $this->name,
            'query'      => $this->query,
            'limit'      => $this->limit,
            'offset'     => $this->offset,
            'sort'       => $this->sort
        ];
    }

    /**
     * Create CursorReader based on stored query, limits and sorting.
     *
     * @param array    $query  Fields and conditions to query by.
     * @param array    $fields Fields of the results to return.
     * @param int|null $limit  Custom limit value.
     * @return DocumentIterator
     * @throws \MongoException
     */
    protected function createCursor($query = [], $fields = [], $limit = null)
    {
        $this->query($query);
        $this->runPagination();

        $cursorReader = new DocumentIterator(
            $this->mongoCollection()->find($this->query, $fields),
            $this->odm,
            $this->odm->collectionClass($this->database, $this->collection),
            $this->sort,
            !empty($limit) ? $limit : $this->limit,
            $this->offset
        );

        if ((!empty($this->limit) || !empty($this->offset)) && empty($this->sort)) {
            //Document can travel in mongo collection
            $this->logger()->warning(
                "MongoDB query executed with limit/offset but without specified sorting."
            );
        }

        if (!$this->mongoDatabase()->isProfiling()) {
            return $cursorReader;
        }

        $queryInfo = ['query' => $this->query, 'sort' => $this->sort];

        if (!empty($this->limit)) {
            $queryInfo['limit'] = !empty($limit) ? (int)$limit : (int)$this->limit;
        }

        if (!empty($this->offset)) {
            $queryInfo['offset'] = (int)$this->offset;
        }

        if ($this->mongoDatabase()->getProfilingLevel() == MongoDatabase::PROFILE_EXPLAIN) {
            $queryInfo['explained'] = $cursorReader->explain();
        }

        $this->logger()->debug(
            "{database}/{collection}: " . json_encode($queryInfo, JSON_PRETTY_PRINT),
            [
                'collection' => $this->name,
                'database'   => $this->database,
                'queryInfo'  => $queryInfo
            ]
        );

        return $cursorReader;
    }

    /**
     * MongoDatabase instance.
     *
     * @return MongoDatabase
     */
    protected function mongoDatabase()
    {
        return $this->odm->db($this->database);
    }

    /**
     * Get associated mongo collection.
     *
     * @return \MongoCollection
     */
    protected function mongoCollection()
    {
        if (!empty($this->collection)) {
            return $this->collection;
        }

        return $this->collection = $this->mongoDatabase()->selectCollection($this->name);
    }
}