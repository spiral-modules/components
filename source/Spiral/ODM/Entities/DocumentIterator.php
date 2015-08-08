<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ODM\Collection;

use Spiral\ODM\Document;
use Spiral\ODM\ODM;

/**
 * Walks thought query result and creates instances of Document on demand.
 *
 * @method array explain()
 */
class DocumentIterator implements \Iterator
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
     * @var string
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
    public function __construct(\MongoCursor $cursor, ODM $odm, $class, array $sort = [], $limit = null, $offset = null)
    {
        $this->cursor = $cursor;
        $this->odm = $odm;
        $this->class = $class;

        !empty($sort) && $this->cursor->sort($sort);
        !empty($limit) && $this->cursor->limit($limit);
        !empty($offset) && $this->cursor->skip($offset);
    }

    /**
     * Sets the fields for a query. Query will return arrays instead of Documents if selection fields are set.
     *
     * @link http://www.php.net/manual/en/mongocursor.fields.php
     * @param array $fields Fields to return (or not return).
     * @throws \MongoCursorException
     * @return $this
     */
    public function fields(array $fields)
    {
        $this->cursor->fields($fields);
        $this->class = [];

        return $this;
    }

    /**
     * {@inheritdoc}
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
     * @return array|Document Returns the next object
     */
    public function getNext()
    {
        $this->cursor->next();

        return $this->current();
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
        if ($result === $this->cursor) {
            return $this;
        }

        return $result;
    }
}