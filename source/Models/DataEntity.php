<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Models;

use Psr\Log\LoggerAwareInterface;
use Spiral\Core\Component;
use Spiral\Debug\Traits\LoggerTrait;
use Spiral\Events\Traits\EventsTrait;
use Spiral\Validation\Traits\ValidatorTrait;
use Spiral\Models\Schemas\EntitySchema;

abstract class DataEntity extends Component implements
    \JsonSerializable,
    \IteratorAggregate,
    \ArrayAccess,
    LoggerAwareInterface
{
    /**
     * Model events and localization.
     */
    use EventsTrait, LoggerTrait, ValidatorTrait;

    /**
     * Such option will be passed to trait initializers when some component requested model schema
     * analysis.
     */
    const SCHEMA_ANALYSIS = 788;

    /**
     * Indication that model was already initiated.
     *
     * @var array
     */
    protected static $initiatedModels = [];

    /**
     * Cache of error messages ordered by their definition parent.
     *
     * @var array
     */
    protected static $messagesCache = [];

    /**
     * List of secured fields, such fields can not be set using setFields() method (only directly).
     *
     * @var array
     */
    protected $secured = [];

    /**
     * Set of fields which can be assigned using setFields() method, if property is empty every field
     * except secured will be assignable. Fields can still be assigned directly using setField() or
     * __set() methods without any limitations.
     *
     * @var array
     */
    protected $fillable = [];

    /**
     * List of hidden fields can not be fetched using publicFields() method (only directly).
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * Indication that validation is required, flag can be set when some field changed or in other
     * conditions when data has to be revalidated.
     *
     * @var bool
     */
    protected $validationRequired = false;

    /**
     * Field getters, will be executed when field are received.
     *
     * @var array
     */
    protected $getters = [];

    /**
     * Field setters, called when field assigned by setField() or setFields().
     *
     * @var array
     */
    protected $setters = [];

    /**
     * Accessors. By using accessor some model value can be mocked up with class "representative"
     * like DateTime for timestamp field. Accessors will be used only on direct field access and will
     * be "serialized" in getFields(), publicFields() methods. Do not use accessors in combination
     * with setters/getters, this is standalone way to manipulate value.
     *
     * @var array
     */
    protected $accessors = [];

    /**
     * Get mutator for specified field. Setters, getters and accessors can be retrieved using this
     * method.
     *
     * @param string $field   Field name.
     * @param string $mutator Mutator type (setter, getter, accessor).
     * @return mixed|null
     */
    protected function getMutator($field, $mutator)
    {
        //We do support 3 mutators: getter, setter and accessor, all of them can be
        //referenced to valid field name by adding "s" at the end
        $mutator = $mutator . 's';

        if (isset($this->{$mutator}[$field]))
        {
            return $this->{$mutator}[$field];
        }

        return null;
    }

    /**
     * Get accessor instance.
     *
     * @param mixed  $value    Value to mock up.
     * @param string $accessor Accessor definition (can be array).
     * @return AccessorInterface
     */
    protected function defineAccessor($value, $accessor)
    {
        $options = null;
        if (is_array($accessor))
        {
            list($accessor, $options) = $accessor;
        }

        return new $accessor($value, $this, $options);
    }

    /**
     * Get one specific field value and apply getter filter to it. You can disable getter filter by
     * providing second argument.
     *
     * @param string $name    Field name.
     * @param bool   $filter  If false no filter will be applied.
     * @param mixed  $default Default value to return if field not set.
     * @return mixed|AccessorInterface
     */
    public function getField($name, $filter = true, $default = null)
    {
        $value = isset($this->fields[$name]) ? $this->fields[$name] : $default;

        if ($value instanceof AccessorInterface)
        {
            return $value;
        }

        if ($accessor = $this->getMutator($name, 'accessor'))
        {
            return $this->fields[$name] = $this->defineAccessor($value, $accessor);
        }

        if ($filter && $filter = $this->getMutator($name, 'getter'))
        {
            try
            {
                return call_user_func($filter, $value);
            }
            catch (\ErrorException $exception)
            {
                $this->logger()->warning("Failed to apply filter to '{name}' field.", compact('name'));

                return null;
            }
        }

        return $value;
    }

    /**
     * Set value to one of field. Setter filter can be disabled by providing last argument.
     *
     * @param string $name   Field name.
     * @param mixed  $value  Value to set.
     * @param bool   $filter If false no filter will be applied (setter or accessor).
     */
    public function setField($name, $value, $filter = true)
    {
        if ($value instanceof AccessorInterface)
        {
            $this->fields[$name] = $value->embed($this);

            return;
        }

        if ($filter && $accessor = $this->getMutator($name, 'accessor'))
        {
            if (!isset($this->fields[$name]))
            {
                $this->fields[$name] = null;
            }

            if (!($this->fields[$name] instanceof AccessorInterface))
            {
                $this->fields[$name] = $this->defineAccessor($this->fields[$name], $accessor);
            }

            $this->fields[$name]->setData($value);

            return;
        }

        if ($filter && $filter = $this->getMutator($name, 'setter'))
        {
            try
            {
                $value = call_user_func($filter, $value);
            }
            catch (\ErrorException $exception)
            {
                $value = call_user_func($filter, null);
                $this->logger()->warning("Failed to apply filter to '{name}' field.", compact('name'));
            }
        }

        $this->fields[$name] = $value;
        $this->validationRequired = true;
    }

    /**
     * Whether a offset exists.
     *
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset An offset to check for.
     * @return bool
     */
    public function __isset($offset)
    {
        return array_key_exists($offset, $this->fields);
    }

    /**
     * Offset to retrieve.
     *
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset The offset to retrieve.
     * @return mixed
     */
    public function __get($offset)
    {
        return $this->getField($offset, true);
    }

    /**
     * Offset to set.
     *
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset The offset to assign the value to.
     * @param mixed $value  The value to set.
     */
    public function __set($offset, $value)
    {
        $this->setField($offset, $value);
    }

    /**
     * Offset to unset.
     *
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset The offset to unset.
     */
    public function __unset($offset)
    {
        unset($this->fields[$offset]);
        $this->validationRequired = true;
    }

    /**
     * Whether a offset exists.
     *
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset An offset to check for.
     * @return boolean
     */
    public function offsetExists($offset)
    {
        return $this->__isset($offset);
    }

    /**
     * Offset to retrieve.
     *
     * @link   http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset The offset to retrieve.
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->getField($offset);
    }

    /**
     * Offset to set.
     *
     * @link   http://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset The offset to assign the value to.
     * @param mixed $value  The value to set.
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->setField($offset, $value);
    }

    /**
     * Offset to unset.
     *
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset The offset to unset.
     * @return void
     */
    public function offsetUnset($offset)
    {
        $this->__unset($offset);
    }

    /**
     * Serialize object data for saving into database. No getters will be applied here.
     *
     * @return mixed
     */
    public function serializeData()
    {
        $result = $this->fields;
        foreach ($result as $field => $value)
        {
            if ($value instanceof AccessorInterface)
            {
                $result[$field] = $value->serializeData();
            }
        }

        return $result;
    }

    /**
     * Get all models fields. All accessors will be automatically converted to their values.
     *
     * @param bool $filter Apply getters.
     * @return array
     */
    public function getFields($filter = true)
    {
        $result = [];
        foreach ($this->fields as $name => &$field)
        {
            $result[$name] = $this->getField($name, $filter);
        }

        return $result;
    }

    /**
     * Check if field assignable.
     *
     * @param string $field
     * @return bool
     */
    protected function isFillable($field)
    {
        return !in_array($field, $this->secured) && !(
            !empty($this->fillable) && !in_array($field, $this->fillable)
        );
    }

    /**
     * Update multiple non-secured model fields. Event "setFields" raised here.
     *
     * @param array|\Traversable $fields
     * @return $this
     */
    public function setFields($fields = [])
    {
        if (!is_array($fields) && !$fields instanceof \Traversable)
        {
            return $this;
        }

        foreach ($this->fire('setFields', $fields) as $name => $field)
        {
            $this->isFillable($field) && $this->setField($name, $field, true);
        }

        return $this;
    }

    /**
     * Get all non secured model fields. Additional processing can be applied to fields here.
     *
     * @return array
     */
    public function publicFields()
    {
        $fields = $this->getFields();
        foreach ($this->hidden as $secured)
        {
            unset($fields[$secured]);
        }

        return $this->fire('publicFields', $fields);
    }

    /**
     * Retrieve an external iterator. An instance of an object implementing Iterator or Traversable.
     *
     * @link http://php.net/manual/en/iteratoraggregate.getiterator.php
     * @return \Traversable
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->getFields());
    }

    /**
     * Request validation.
     *
     * @return $this
     */
    public function requestValidation()
    {
        $this->validationRequired = true;

        return $this;
    }

    /**
     * Validating model data using validation rules, all errors will be stored in model errors array.
     * Errors will not be erased between function calls.
     *
     * @return bool
     */
    protected function validate()
    {
        if (empty($this->validates))
        {
            $this->validationRequired = false;
        }
        elseif ($this->validationRequired)
        {
            $this->fire('validation');

            $this->errors = $this->getValidator()->getErrors();
            $this->validationRequired = false;

            //Cleaning memory
            $this->validator->setData([]);
            $this->errors = $this->fire('validated', $this->errors);
        }

        return empty($this->errors);
    }

    /**
     * Initialize model by calling it's methods named using pattern __init*. Such methods can be
     * protected and will be called only once, on first model constructing.
     *
     * @param mixed $options Custom options passed to initializer. Providing option will force
     *                       initialization methods even if entity already initiated.
     */
    protected static function initialize($options = null)
    {
        if (isset(self::$initiatedModels[$class = get_called_class()]) && empty($options))
        {
            return;
        }

        foreach (get_class_methods($class) as $method)
        {
            if (substr($method, 0, 4) === 'init' && $method != 'initialize')
            {
                forward_static_call(['static', $method], $options);
            }
        }

        self::$initiatedModels[$class] = true;
    }

    /**
     * Prepare document property before caching it ORM schema. This method fire event "property" and
     * sends SCHEMA_ANALYSIS option to trait initializers. Method can be used to create custom filters,
     * schema values and etc.
     *
     * @param EntitySchema $schema
     * @param string       $property Model property name.
     * @param mixed        $value    Model property value, will be provided in an inherited form.
     * @return mixed
     */
    public static function describeProperty(EntitySchema $schema, $property, $value)
    {
        static::initialize(self::SCHEMA_ANALYSIS);

        return static::events()->fire('describe', compact('schema', 'property', 'value'))['value'];
    }

    /**
     * Destructing model fields, filters and validator.
     */
    public function __destruct()
    {
        $this->fields = [];
        $this->validator = null;
    }

    /**
     * (PHP 5 > 5.4.0)
     * Specify data which should be serialized to JSON.
     *
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed
     */
    public function jsonSerialize()
    {
        return $this->fire('jsonSerialize', $this->publicFields());
    }
}