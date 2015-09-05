<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ODM\Accessors;

use Spiral\ODM\Document;
use Spiral\ODM\DocumentAccessorInterface;
use Spiral\ODM\Exceptions\AccessorException;
use Spiral\ODM\ODM;

/**
 * Simple ODM accessor with ability to mock access to array field. ScalarArray support atomic
 * operations and performs type normalization.
 */
class ScalarArray implements DocumentAccessorInterface, \IteratorAggregate, \Countable, \ArrayAccess
{
    /**
     * No typecasting will be performed if primary array type defined as "mixed".
     */
    const MIXED_TYPE = 'mixed';

    /**
     * Scalar array type name.
     *
     * @var string
     */
    private $type = '';

    /**
     * Array data.
     *
     * @var array
     */
    protected $data = [];

    /**
     * When solid state is enabled no atomic operations will be pushed to databases and array will
     * be saved as one big set. Enabled by default.
     *
     * @var bool
     */
    protected $solidState = true;

    /**
     * Indication that were updated.
     *
     * @var array
     */
    protected $updated = false;

    /**
     * Low level atomic operations.
     *
     * @var array
     */
    protected $atomics = [];

    /**
     * @invisible
     * @var Document
     */
    protected $parent = null;

    /**
     * Supported type filters. No boolean include, cos who the hell need array of booleans.
     *
     * @var array
     */
    protected $filters = [
        'int'      => 'intval',
        'float'    => 'floatval',
        'string'   => 'strval',
        'MongoId'  => [ODM::class, 'mongoID'],
        '\MongoId' => [ODM::class, 'mongoID']
    ];

    /**
     * {@inheritdoc}
     *
     * @param mixed $type Type to be filtered by. Set to null or mixed to allow any type.
     */
    public function __construct($data, $parent = null, ODM $odm = null, $type = self::MIXED_TYPE)
    {
        $this->parent = $parent;
        if (!is_array($data) && $data !== null) {
            throw new AccessorException("ScalarArray support only scalar arrays."); //:)
        }

        $this->data = is_array($data) ? $data : [];
        $this->type = !empty($type) ? $type : self::MIXED_TYPE;

        if ($this->type != self::MIXED_TYPE && !isset($this->filters[$this->type])) {
            throw new AccessorException("Unknown/unsupported ScalarArray value type '{$type}'.");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function defaultValue()
    {
        $result = [];
        foreach ($this->data as $value) {
            $value = $this->filter($value);

            if ($value !== null) {
                $result[] = $value;
            }
        }

        return $result;
    }

    /**
     * ScalarArray values type.
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * When solid state is enabled no atomic operations will be pushed to databases and array will
     * be saved as one big set request.
     *
     * @param bool $solidState
     * @return $this
     */
    public function solidState($solidState)
    {
        $this->solidState = $solidState;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function embed($parent)
    {
        if (!$parent instanceof Document) {
            throw new AccessorException("ScalarArrays can be embedded only into Documents.");
        }

        if ($parent === $this->parent) {
            return $this;
        }

        $accessor = clone $this;
        $accessor->parent = $parent;
        $accessor->solidState = $accessor->updated = true;

        return $accessor;
    }

    /**
     * {@inheritdoc}
     *
     * @return $this
     */
    public function setData($data)
    {
        $this->updated = $this->solidState = true;

        if (!is_array($data)) {
            //Ignoring this set
            return $this;
        }

        $this->data = [];
        foreach ($data as $value) {
            if (($value = $this->filter($value)) !== null) {
                $this->data[] = $value;
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function serializeData()
    {
        return $this->data;
    }

    /**
     * {@inheritdoc}
     */
    public function hasUpdates()
    {
        return $this->updated || $this->atomics;
    }

    /**
     * {@inheritdoc}
     */
    public function flushUpdates()
    {
        $this->updated = false;
        $this->atomics = [];
    }

    /**
     * {@inheritdoc}
     */
    public function buildAtomics($container = '')
    {
        if (!$this->hasUpdates()) {
            return [];
        }

        if ($this->solidState) {
            //We don't care about atomics
            return [Document::ATOMIC_SET => [$container => $this->serializeData()]];
        }

        $atomics = [];
        foreach ($this->atomics as $operation => $value) {
            $atomics = [$operation => [$container => $value]];
        }

        return $atomics;
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return count($this->data);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset)
    {
        return isset($this->data[$offset]);
    }

    /**
     * {@inheritdoc}
     * @throws AccessorException
     */
    public function offsetGet($offset)
    {
        if (!isset($this->data[$offset])) {
            throw new AccessorException("Undefined offset '{$offset}'.");
        }

        return $this->data[$offset];
    }

    /**
     * {@inheritdoc}
     * @throws AccessorException
     */
    public function offsetSet($offset, $value)
    {
        if (!$this->solidState) {
            throw new AccessorException(
                "Direct offset operations can not be performed for ScalarArray in non solid state."
            );
        }

        if (($value = $this->filter($value)) === null) {
            return;
        }

        $this->updated = true;
        if (is_null($offset)) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }

    /**
     * {@inheritdoc}
     * @throws AccessorException
     */
    public function offsetUnset($offset)
    {
        if (!$this->solidState) {
            throw new AccessorException(
                "Direct offset operations can not be performed for ScalarArray in non solid state."
            );
        }

        $this->updated = true;
        unset($this->data[$offset]);
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->data);
    }

    /**
     * Clear all values.
     *
     * @return $this
     */
    public function clear()
    {
        $this->solidState = $this->updated = true;
        $this->data = [];

        return $this;
    }

    /**
     * Alias for atomic operation $push. Only values passed type filter will be added.
     *
     * @param mixed $value
     * @return $this
     */
    public function push($value)
    {
        if (($value = $this->filter($value)) === null) {
            return $this;
        }

        array_push($this->data, $value);
        $this->atomics['$push']['$each'][] = $value;

        return $this;
    }

    /**
     * Alias for atomic operation $addToSet. Only values passed type filter will be added.
     *
     * @param mixed $value
     * @return $this
     */
    public function addToSet($value)
    {
        if (($value = $this->filter($value)) === null) {
            return $this;
        }

        !in_array($value, $this->data) && array_push($this->data, $value);
        $this->atomics['$addToSet']['$each'] = $value;

        return $this;
    }

    /**
     * Alias for atomic operation $pull. Only values passed type filter will be added.
     *
     * @param mixed $value
     * @return $this
     */
    public function pull($value)
    {
        if (($value = $this->filter($value)) === null) {
            return $this;
        }

        $this->data = array_filter($this->data, function ($item) use ($value) {
            return $item != $value;
        });

        $this->atomics['$pull'] = $value;

        return $this;
    }


    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return $this->serializeData();
    }

    /**
     * @return Object
     */
    public function __debugInfo()
    {
        return (object)[
            'data'    => $this->serializeData(),
            'type'    => $this->getType(),
            'atomics' => $this->buildAtomics('@scalarArray')
        ];
    }

    /**
     * Filter value before embedding into array.
     *
     * @param mixed $value
     * @return mixed
     */
    protected function filter($value)
    {
        if ($this->type == self::MIXED_TYPE) {
            return $value;
        }

        if (!is_scalar($value)) {
            return null;
        }

        try {
            return call_user_func($this->filters[$this->type], $value);
        } catch (\Exception $exception) {
            return null;
        }
    }
}