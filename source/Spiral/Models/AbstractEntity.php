<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Models;

use Doctrine\Common\Inflector\Inflector;
use Spiral\Models\Events\EntityEvent;
use Spiral\Models\Exceptions\AccessorExceptionInterface;
use Spiral\Models\Exceptions\EntityException;
use Spiral\Models\Exceptions\FieldExceptionInterface;
use Spiral\Validation\ValueInterface;

/**
 * AbstractEntity with ability to define field mutators and access
 */
abstract class AbstractEntity extends MutableObject implements
    EntityInterface,
    \JsonSerializable,
    \IteratorAggregate,
    \ArrayAccess,
    ValueInterface
{
    /**
     * Field format declares how entity must process magic setters and getters. Available values:
     * camelCase, tableize.
     */
    const FIELD_FORMAT = 'camelCase';

    /**
     * Field mutators.
     */
    const MUTATOR_GETTER   = 'getter';
    const MUTATOR_SETTER   = 'setter';
    const MUTATOR_ACCESSOR = 'accessor';

    /**
     * @var array
     */
    private $fields = [];

    /**
     * @param array $fields
     */
    public function __construct(array $fields)
    {
        $this->fields = $fields;
        parent::__construct();
    }

    /**
     * Routes user function in format of (get|set)FieldName into (get|set)Field(fieldName, value).
     *
     * @see getFeld()
     * @see setField()
     *
     * @param string $method
     * @param array  $arguments
     *
     * @return $this|mixed|null|AccessorInterface
     *
     * @throws EntityException
     */
    public function __call($method, array $arguments)
    {
        if (method_exists($this, $method)) {
            throw new EntityException(
                "Method name '{$method}' is ambiguous and can not be used as magic setter"
            );
        }

        if (strlen($method) <= 3) {
            //Get/set needs exactly 0-1 argument
            throw new EntityException("Undefined method {$method}");
        }

        $field = substr($method, 3);

        switch (static::FIELD_FORMAT) {
            case 'camelCase':
                $field = Inflector::camelize($field);
                break;
            case 'tableize':
                $field = Inflector::tableize($field);
                break;
            default:
                throw new EntityException(
                    "Undefined field format '" . static::FIELD_FORMAT . "'"
                );
        }

        switch (substr($method, 0, 3)) {
            case 'get':
                return $this->getField($field);
            case 'set':
                if (count($arguments) === 1) {
                    $this->setField($field, $arguments[0]);

                    //setFieldA($a)->setFieldB($b)
                    return $this;
                }
        }

        throw new EntityException("Undefined method {$method}.");
    }

    /**
     * {@inheritdoc}
     */
    public function hasField($name)
    {
        return array_key_exists($name, $this->fields);
    }

    /**
     * {@inheritdoc}
     *
     * @param bool $filter If false, associated field setter or accessor will be ignored.
     *
     * @throws AccessorExceptionInterface
     */
    public function setField($name, $value, $filter = true)
    {
        if ($value instanceof AccessorInterface) {
            $this->fields[$name] = $value->embed($this);

            return;
        }

        if (!$filter) {
            $this->fields[$name] = $value;

            return;
        }

        if (!empty($accessor = $this->getMutator($name, self::MUTATOR_ACCESSOR))) {
            $field = $this->fields[$name];
            if (empty($field) || !($field instanceof AccessorInterface)) {
                $this->fields[$name] = $field = $this->createAccessor($accessor, $field);
            }

            //Letting accessor to set value
            $field->setValue($value);

            return;
        }

        if (!empty($setter = $this->getMutator($name, self::MUTATOR_SETTER))) {
            try {
                $this->fields[$name] = call_user_func($setter, $value);
            } catch (\ErrorException $exception) {
                //Exceptional situation, we are choosing to keep original field value
            }
        } else {
            $this->fields[$name] = $value;
        }
    }

    /**
     * {@inheritdoc}
     *
     * @param bool $filter If false, associated field getter will be ignored.
     *
     * @throws AccessorExceptionInterface
     */
    public function getField($name, $default = null, $filter = true)
    {
        $value = $this->hasField($name) ? $this->fields[$name] : $default;

        if ($value instanceof AccessorInterface) {
            //Accessor will deal with setting value by itself
            return $value;
        }

        if (!empty($accessor = $this->getMutator($name, self::MUTATOR_ACCESSOR))) {
            return $this->fields[$name] = $this->createAccessor($accessor, $value);
        }

        if ($filter && !empty($getter = $this->getMutator($name, self::MUTATOR_GETTER))) {
            try {
                return call_user_func($getter, $value);
            } catch (\ErrorException $exception) {
                //Trying to filter null value, every filter must support it
                return call_user_func($getter, null);
            }
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     *
     * @see   $fillable
     * @see   $secured
     * @see   isFillable()
     *
     * @param array|\Traversable $fields
     * @param bool               $all Fill all fields including non fillable.
     *
     * @return $this
     *
     * @throws AccessorExceptionInterface
     */
    public function setFields($fields = [], $all = false)
    {
        if (!is_array($fields) && !$fields instanceof \Traversable) {
            return $this;
        }

        foreach ($fields as $name => $value) {
            if ($all || $this->isFillable($name)) {
                try {
                    $this->setField($name, $value, true);
                } catch (FieldExceptionInterface $e) {
                    //We are supressing field setting exceptions
                }
            }
        }

        return $this;
    }

    /**
     * @return array
     */
    protected function getKeys()
    {
        return array_keys($this->fields);
    }

    /**
     * {@inheritdoc}
     *
     * Every getter and accessor will be applied/constructed if filter argument set to true.
     *
     * @param bool $filter
     *
     * @throws AccessorExceptionInterface
     */
    public function getFields($filter = true)
    {
        $result = [];
        foreach ($this->fields as $name => $field) {
            $result[$name] = $this->getField($name, null, $filter);
        }

        return $result;
    }

    /**
     * @param mixed $offset
     *
     * @return bool
     */
    public function __isset($offset)
    {
        return $this->hasField($offset);
    }

    /**
     * @param mixed $offset
     *
     * @return mixed
     */
    public function __get($offset)
    {
        return $this->getField($offset);
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function __set($offset, $value)
    {
        $this->setField($offset, $value);
    }

    /**
     * @param mixed $offset
     */
    public function __unset($offset)
    {
        unset($this->fields[$offset]);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset)
    {
        return $this->__isset($offset);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($offset)
    {
        return $this->getField($offset);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($offset, $value)
    {
        $this->setField($offset, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($offset)
    {
        $this->__unset($offset);
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->getFields());
    }

    /**
     * Serialize entity data into plain array.
     *
     * @return array
     *
     * @throws AccessorExceptionInterface
     */
    public function serializeData()
    {
        $result = [];
        foreach ($this->fields as $field => $value) {
            if ($value instanceof ValueInterface) {
                $result[$field] = $value->serializeData();
            } else {
                $result[] = $value;
            }
        }

        return $result;
    }

    /**
     * Public entity fields.
     *
     * @return array|AccessorInterface[]
     */
    abstract public function publicFields();

    /**
     * {@inheritdoc}
     *
     * By default use publicFields to be json serialized.
     */
    public function jsonSerialize()
    {
        return $this->publicFields();
    }

    /**
     * Destruct data entity.
     */
    public function __destruct()
    {
        $this->fields = [];
    }

    /**
     * Check if field is fillable.
     *
     * @param string $field
     * @return bool
     */
    abstract protected function isFillable($field);

    /**
     * Get mutator associated with given field.
     *
     * @param string $field
     * @param string $type See MUTATOR_* constants
     * @return string
     */
    abstract protected function getMutator($field, $type);

    /**
     * Create instance of field accessor.
     *
     * @param string $accessor
     * @param mixed  $value
     *
     * @return AccessorInterface
     *
     * @throws AccessorExceptionInterface
     */
    protected function createAccessor($accessor, $value)
    {
        return new $accessor($value, $this);
    }

    /**
     * {@inheritdoc}
     */
    public static function create($fields = [])
    {
        /**
         * @var self $entity
         */
        $entity = (new static([]))->setFields($fields);
        $entity->dispatch('created', new EntityEvent($entity));

        return $entity;
    }
}