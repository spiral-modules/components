<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ODM\Entities;

use Spiral\ODM\ActiveDocument;
use Spiral\ODM\Exceptions\DefinitionException;
use Spiral\ODM\Exceptions\ODMException;
use Spiral\ODM\ODM;

/**
 * Walks thought query result and creates instances of Document on demand. Class decorates methods
 * of MongoCursor.
 *
 * @see MongoCursor
 *
 * Wrapped methods.
 * @method bool hasNext()
 * @method static limit($number)
 * @method static batchSize($number)
 * @method static skip($number)
 * @method static addOption($key, $value)
 * @method static snapshot()
 * @method static sort($fields)
 * @method static hint($keyPattern)
 * @method array  explain()
 * @method static setFlag($bit, $set)
 * @method static slaveOkay($okay)
 * @method static tailable($tail)
 * @method static immortal($liveForever)
 * @method static awaitData($wait)
 * @method static partial($okay)
 * @method array getReadPreference()
 * @method static setReadPreference($read_preference, array $tags)
 * @method static timeout()
 * @method static info()
 * @method bool dead()
 * @method static reset()
 * @method int count($foundOnly)
 */
class DocumentCursor implements \Iterator, \JsonSerializable
{
    /**
     * MongoCursor instance.
     *
     * @var \MongoCursor
     */
    protected $cursor = null;

    /**
     * ODM component.
     *
     * @var ODM
     */
    protected $odm = null;

    /**
     * Document class being iterated.
     *
     * @var string|null
     */
    protected $class = '';

    /**
     * @param \MongoCursor $cursor
     * @param ODM          $odm
     * @param string       $class
     * @param array        $sort
     * @param int          $limit
     * @param int          $offset
     */
    public function __construct(
        \MongoCursor $cursor,
        ODM $odm,
        $class,
        array $sort = [],
        $limit = null,
        $offset = null
    ) {
        $this->cursor = $cursor;
        $this->odm = $odm;
        $this->class = $class;

        !empty($sort) && $this->cursor->sort($sort);
        !empty($limit) && $this->cursor->limit($limit);
        !empty($offset) && $this->cursor->skip($offset);
    }

    /**
     * Sets the fields for a query. Query will return arrays instead of Documents if selection
     * fields are set.
     *
     * @link http://www.php.net/manual/en/mongocursor.fields.php
     * @param array $fields Fields to return (or not return).
     * @throws \MongoCursorException
     * @return $this
     */
    public function fields(array $fields)
    {
        $this->cursor->fields($fields);
        $this->class = null;

        return $this;
    }

    /**
     * Select all documents.
     *
     * @return ActiveDocument[]
     * @throws ODMException
     * @throws DefinitionException
     */
    public function all()
    {
        $result = [];
        foreach ($this as $document) {
            $result[] = $document;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @return array|ActiveDocument
     * @throws ODMException
     * @throws DefinitionException
     */
    public function current()
    {
        $fields = $this->cursor->current();
        if (empty($this->class)) {
            return $fields;
        }

        return $fields ? $this->odm->document($this->class, $fields) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function next()
    {
        $this->cursor->next();
    }

    /**
     * {@inheritdoc}
     */
    public function key()
    {
        return $this->cursor->key();
    }

    /**
     * {@inheritdoc}
     */
    public function valid()
    {
        return $this->cursor->valid();
    }

    /**
     * {@inheritdoc}
     */
    public function rewind()
    {
        $this->cursor->rewind();
    }

    /**
     * Return the next object to which this cursor points, and advance the cursor
     *
     * @link http://www.php.net/manual/en/mongocursor.getnext.php
     * @throws \MongoConnectionException
     * @throws \MongoCursorTimeoutException
     * @return array|ActiveDocument Returns the next object
     */
    public function getNext()
    {
        $this->cursor->next();

        return $this->current();
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        $result = [];
        foreach ($this as $document) {
            $result[] = $document->publicFields();
        }

        return $result;
    }

    /**
     * Forward call to cursor.
     *
     * @param string $method
     * @param array  $arguments
     * @return mixed
     */
    public function __call($method, array $arguments)
    {
        $result = call_user_func_array([$this->cursor, $method], $arguments);
        if ($result === $this->cursor || $result === null) {
            return $this;
        }

        return $result;
    }
}