<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Models;

use Doctrine\Common\Inflector\Inflector;
use Spiral\Core\Component;
use Spiral\Core\Exceptions\MissingContainerException;
use Spiral\Models\Exceptions\AccessorExceptionInterface;
use Spiral\Models\Exceptions\EntityException;
use Spiral\Models\Reflections\ReflectionEntity;
use Spiral\Validation\Exceptions\ValidationException;
use Spiral\Validation\Traits\ValidatorTrait;
use Spiral\Validation\ValidatesInterface;

/**
 * DataEntity in spiral used to represent basic data set with validation rules, filters and
 * accessors. Most of spiral models (ORM and ODM, HttpFilters) will extend data entity.  In addition
 * it creates magic set of getters and setters for every field name (see validator trait) in model.
 */
abstract class DataEntity extends Component implements
    \JsonSerializable,
    \IteratorAggregate,
    \ArrayAccess,
    ValidatesInterface
{
    /**
     * Every entity can be validated, in addition validation trait will load Translator and Event
     * traits.
     */
    use ValidatorTrait;

    /**
     * Every entity might have set of traits which can be initiated manually or at moment of construction
     * model instance. Array will store already initiated model names.
     *
     * @var array
     */
    private static $initiated = [];

    /**
     * Indicates that model data have been validated since last change.
     *
     * @var bool
     */
    protected $validated = true;

    /**
     * List of fields must be hidden from publicFields() method.
     *
     * @see publicFields()
     * @var array
     */
    protected $hidden = [];

    /**
     * Set of fields allowed to be filled using setFields() method.
     *
     * @see setFields()
     * @var array
     */
    protected $fillable = [];

    /**
     * List of fields not allowed to be filled by setFields() method. By default no fields can be
     * set. Replace with and empty array to allow all fields.
     *
     * @see setFields()
     * @var array|string
     */
    protected $secured = '*';

    /**
     * @see setField()
     * @var array
     */
    protected $setters = [];

    /**
     * @see getField()
     * @var array
     */
    protected $getters = [];

    /**
     * Accessor used to mock field data and filter every request thought itself.
     *
     * @see getField()
     * @see setField()
     * @var array
     */
    protected $accessors = [];

    /**
     * Routes user function in format of (get|set)FieldName into (get|set)Field(fieldName, value).
     *
     * @see getFeld()
     * @see setField()
     * @param string $method
     * @param array  $arguments
     * @return $this|mixed|null|AccessorInterface
     * @throws EntityException
     */
    public function __call($method, array $arguments)
    {
        if (count($arguments) <= 1 && strlen($method) <= 3) {
            //Get/set needs exactly 1 argument
            throw new EntityException("Undefined method {$method}.");
        }

        //get/set
        $operation = substr($method, 0, 3);
        if ($operation === 'get' && count($arguments) === 0) {
            return $this->getField(Inflector::camelize(substr($method, 3)));
        }

        if ($operation === 'set' && count($arguments) === 1) {
            $this->setField(Inflector::camelize(substr($method, 3)), $arguments[0]);

            //setFieldA($a)->setFieldB($b)
            return $this;
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
     * @throws AccessorExceptionInterface
     */
    public function setField($name, $value, $filter = true)
    {
        if ($value instanceof AccessorInterface) {
            $this->fields[$name] = $value->embed($this);

            return;
        }

        $this->validated = false;

        if (!$filter) {
            $this->fields[$name] = $value;

            return;
        }

        if ($accessor = $this->getMutator($name, 'accessor')) {
            $field = $this->fields[$name];
            if (empty($field) || !($field instanceof AccessorInterface)) {
                $this->fields[$name] = $field = $this->createAccessor($accessor, $field);
            }

            $field->setData($value);
        }

        if ($setter = $this->getMutator($name, 'setter')) {
            try {
                $this->fields[$name] = call_user_func($setter, $value);
            } catch (\ErrorException $exception) {
                $this->fields[$name] = call_user_func($setter, null);
            }
        }
    }

    /**
     * {@inheritdoc}
     *
     * @param bool $filter If false, associated field getter will be ignored.
     * @throws AccessorExceptionInterface
     */
    public function getField($name, $default = null, $filter = true)
    {
        $value = isset($this->fields[$name]) ? $this->fields[$name] : $default;

        if ($value instanceof AccessorInterface) {
            return $value;
        }

        if ($accessor = $this->getMutator($name, 'accessor')) {
            return $this->fields[$name] = $this->createAccessor($accessor, $value);
        }

        if ($filter && $getter = $this->getMutator($name, 'getter')) {
            try {
                return call_user_func($getter, $value);
            } catch (\ErrorException $exception) {
                return null;
            }
        }

        return $value;
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function __isset($offset)
    {
        return $this->hasField($offset);
    }

    /**
     * @param mixed $offset
     * @return mixed
     * @throws AccessorExceptionInterface
     */
    public function __get($offset)
    {
        return $this->getField($offset);
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     * @throws AccessorExceptionInterface
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
        $this->validated = false;
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
     *
     * @throws AccessorExceptionInterface
     */
    public function offsetGet($offset)
    {
        return $this->getField($offset);
    }

    /**
     * {@inheritdoc}
     *
     * @throws AccessorExceptionInterface
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
     * {@inheritdoc}
     *
     * @see   $fillable
     * @see   $secured
     * @see   isFillable()
     * @param array|\Traversable $fields
     * @return $this
     * @throws AccessorExceptionInterface
     * @event setFields($fields)
     */
    public function setFields($fields = [])
    {
        if (!is_array($fields) && !$fields instanceof \Traversable) {
            return $this;
        }

        foreach ($this->fire('setFields', $fields) as $name => $field) {
            $this->isFillable($name) && $this->setField($name, $field, true);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * Every getter and accessor will be applied/constructed if filter argument set to true.
     *
     * @param bool $filter
     * @throws AccessorExceptionInterface
     */
    public function getFields($filter = true)
    {
        $result = [];
        foreach ($this->fields as $name => $field) {
            $result[$name] = $this->getField($name, $filter);
        }

        return $result;
    }

    /**
     * Get model fields but exclude hidden one.
     *
     * @see   $hidden
     * @see   getFields()
     * @return array
     * @throws AccessorExceptionInterface
     * @event publicFields($publicFields)
     */
    public function publicFields()
    {
        $fields = $this->getFields();
        foreach ($this->hidden as $secured) {
            unset($fields[$secured]);
        }

        return $this->fire('publicFields', $fields);
    }

    /**
     * Serialize entity data into plain array.
     *
     * @return array
     * @throws AccessorExceptionInterface
     */
    public function serializeData()
    {
        $result = $this->fields;
        foreach ($result as $field => $value) {
            if ($value instanceof AccessorInterface) {
                $result[$field] = $value->serializeData();
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @event jsonSerialize($publicFields)
     */
    public function jsonSerialize()
    {
        return $this->fire('jsonSerialize', $this->publicFields());
    }

    /**
     * Entity must re-validate data.
     *
     * @return $this
     */
    public function invalidate()
    {
        $this->validated = false;

        return $this;
    }

    /**
     * Validate model fields.
     *
     * @return bool
     * @throws ValidationException
     * @throws MissingContainerException
     * @event validation()
     * @event validated($errors)
     */
    protected function validate()
    {
        if (empty($this->validates)) {
            $this->validated = true;
        } elseif (!$this->validated) {
            $this->fire('validation');

            $this->errors = $this->validator()->getErrors();
            $this->validated = empty($this->errors);

            //Cleaning memory
            $this->validator->setData([]);

            $this->errors = $this->fire('validated', $this->errors);
        }

        return empty($this->errors);
    }

    /**
     * Check if field can be set using setFields() method.
     *
     * @see   setField()
     * @see   $fillable
     * @see   $secured
     * @param string $field
     * @return bool
     */
    protected function isFillable($field)
    {
        if (!empty($this->fillable)) {
            return in_array($field, $this->fillable);
        }

        if ($this->secured === '*') {
            return false;
        }

        return !in_array($field, $this->secured);
    }

    /**
     * Check and return name of mutator (getter, setter, accessor) associated with specific field.
     *
     * @param string $field
     * @param string $mutator Mutator type (setter, getter, accessor).
     * @return mixed|null
     */
    protected function getMutator($field, $mutator)
    {
        //We do support 3 mutators: getter, setter and accessor, all of them can be
        //referenced to valid field name by adding "s" at the end
        $mutator = $mutator . 's';

        if (isset($this->{$mutator}[$field])) {
            return $this->{$mutator}[$field];
        }

        return null;
    }

    /**
     * Create instance of field accessor.
     *
     * @param string $accessor
     * @param mixed  $value
     * @return AccessorInterface
     * @throws AccessorExceptionInterface
     */
    protected function createAccessor($accessor, $value)
    {
        return new $accessor($value, $this);
    }

    /**
     * Destruct data entity.
     */
    public function __destruct()
    {
        $this->fields = [];
        $this->validator = null;
    }

    /**
     * Method used while entity static analysis to describe model related property using even dispatcher
     * and associated model traits.
     *
     * @param ReflectionEntity $schema
     * @param string           $property
     * @param mixed            $value
     * @return mixed Returns filtered value.
     * @event describe($property, $value, EntitySchema $schema)
     */
    public static function describeProperty(ReflectionEntity $schema, $property, $value)
    {
        static::initialize(true);

        //Clarifying property value using traits or other listeners
        return static::events()->fire(
            'describe',
            compact('property', 'value', 'schema')
        )['value'];
    }

    /**
     * Initiate associated model traits. System will look for static method with "init" prefix.
     *
     * @param bool $analysis Must be set to true while static analysis.
     */
    protected static function initialize($analysis = false)
    {
        if (isset(self::$initiated[$class = static::class]) && !$analysis) {
            return;
        }

        foreach (get_class_methods($class) as $method) {
            if (substr($method, 0, 4) === 'init' && $method != 'initialize') {
                forward_static_call(['static', $method], $analysis);
            }
        }

        self::$initiated[$class] = true;
    }

    /**
     * Clear initiated objects list.
     */
    public static function resetInitiated()
    {
        self::$initiated = [];
    }
}