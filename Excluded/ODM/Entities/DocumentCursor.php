<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\ODM\Entities;

use Spiral\ODM\Document;
use Spiral\ODM\Exceptions\DefinitionException;
use Spiral\ODM\Exceptions\ODMException;
use Spiral\ODM\ODM;
use Spiral\ODM\ODMInterface;

/**
 * Walks thought query result and creates instances of Document on demand. Class decorates methods
 * of MongoCursor.
 *
 * @see MongoCursor
 *
 * Wrapped methods.
 *
 * @method bool hasNext()
 * @method $this limit($number)
 * @method $this batchSize($number)
 * @method $this skip($number)
 * @method $this addOption($key, $value)
 * @method $this snapshot()
 * @method $this sort($fields)
 * @method $this hint($keyPattern)
 * @method array explain()
 * @method $this setFlag($bit, $set)
 * @method $this slaveOkay($okay)
 * @method $this tailable($tail)
 * @method $this immortal($liveForever)
 * @method $this awaitData($wait)
 * @method $this partial($okay)
 * @method array getReadPreference()
 * @method $this setReadPreference($read_preference, array $tags)
 * @method $this timeout()
 * @method array info()
 * @method bool dead()
 * @method $this reset()
 */
class DocumentCursor implements \Iterator, \JsonSerializable, \Countable
{
    /**
     * MongoCursor instance.
     *
     * @var \MongoCursor
     */
    private $cursor = null;

    /**
     * Document class being iterated.
     *
     * @var string|null
     */
    protected $class = '';

    /**
     * @var ODMInterface
     */
    private $odm = null;

    /**
     * @param \MongoCursor $cursor
     * @param string|null  $class
     * @param ODMInterface $odm
     */
    public function __construct(\MongoCursor $cursor, $class, ODMInterface $odm)
    {
        $this->cursor = $cursor;
        $this->odm = $odm;
        $this->class = $class;
    }

    /**
     * Sets the fields for a query. Query will return arrays instead of Documents if selection
     * fields are set.
     *
     * @link http://www.php.net/manual/en/mongocursor.fields.php
     *
     * @param array $fields Fields to return (or not return).
     *
     * @throws \MongoCursorException
     *
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
     * @return Document[]
     *
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
     * @param bool $foundOnly
     * @return int
     */
    public function count($foundOnly = true)
    {
        //Found only true
        return $this->cursor->count($foundOnly);
    }

    /**
     * {@inheritdoc}
     *
     * @return array|Document
     *
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
     * Return the next object to which this cursor points, and advance the cursor.
     *
     * @link http://www.php.net/manual/en/mongocursor.getnext.php
     *
     * @throws \MongoConnectionException
     * @throws \MongoCursorTimeoutException
     *
     * @return array|Document Returns the next object
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
     *
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