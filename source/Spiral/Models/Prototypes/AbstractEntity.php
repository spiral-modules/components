<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Models\Prototypes;

use Spiral\Models\AccessorInterface;
use Spiral\Models\EntityInterface;
use Spiral\Models\Exceptions\AccessorExceptionInterface;
use Spiral\Models\Exceptions\EntityException;
use Spiral\Models\Exceptions\FieldExceptionInterface;
use Spiral\Models\PublishableInterface;
use Spiral\Models\Traits\EventsTrait;
use Spiral\ODM\Exceptions\FieldException;

/**
 * AbstractEntity with ability to define field mutators and access
 */
abstract class AbstractEntity extends MutableObject implements
    EntityInterface,
    \JsonSerializable,
    \IteratorAggregate,
    AccessorInterface,
    PublishableInterface
{
    use EventsTrait;

    /**
     * Field mutators.
     *
     * @private
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
    public function __construct(array $fields = [])
    {
        $this->fields = $fields;

        //Initiating mutable object
        static::initialize(false);
    }

    /**
     * AccessorInterface dependency.
     *
     * {@inheritdoc}
     */
    public function stateValue($data)
    {
        return $this->setFields($data);
    }

    /**
     * AccessorInterface dependency.
     *
     * {@inheritdoc}
     */
    public function packValue()
    {
        return $this->packFields();
    }

    /**
     * {@inheritdoc}
     */
    public function hasField(string $name): bool
    {
        return array_key_exists($name, $this->fields);
    }

    /**
     * {@inheritdoc}
     *
     * @param bool $filter If false, associated field setter or accessor will be ignored.
     *
     * @throws AccessorExceptionInterface
     * @throws FieldException
     */
    public function setField(string $name, $value, bool $filter = true)
    {
        if ($value instanceof AccessorInterface) {
            //In case of non scalar values filters must be bypassed
            $this->fields[$name] = clone $value;

            return;
        }

        if (!$filter || (is_null($value) && $this->isNullable($name))) {
            //Bypassing all filters
            $this->fields[$name] = $value;

            return;
        }

        //Checking if field have accessor
        $accessor = $this->getMutator($name, self::MUTATOR_ACCESSOR);

        if (!empty($accessor)) {
            $field = $this->fields[$name];
            if (empty($field) || !($field instanceof AccessorInterface)) {
                $this->fields[$name] = $field = $this->createAccessor($accessor, $name, $value);
            }

            //Letting accessor to set value
            $field->stateValue($value);

            return;
        }

        //Checking field setter if any
        $setter = $this->getMutator($name, self::MUTATOR_SETTER);

        if (!empty($setter)) {
            try {
                $this->fields[$name] = call_user_func($setter, $value);
            } catch (\Exception $e) {
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
    public function getField(string $name, $default = null, bool $filter = true)
    {
        $value = $this->hasField($name) ? $this->fields[$name] : $default;

        if ($value instanceof AccessorInterface || (is_null($value) && $this->isNullable($name))) {
            return $value;
        }

        //Checking if field have accessor (decorator)
        $accessor = $this->getMutator($name, self::MUTATOR_ACCESSOR);

        if (!empty($accessor)) {
            return $this->fields[$name] = $this->createAccessor($accessor, $name, $value);
        }

        //Checking for getter
        $getter = $this->getMutator($name, self::MUTATOR_GETTER);

        if ($filter && !empty($getter)) {
            try {
                return call_user_func($getter, $value);
            } catch (\Exception $e) {
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
    public function setFields($fields = [], bool $all = false)
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
    protected function getKeys(): array
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
    public function getFields(bool $filter = true): array
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
    public function getIterator(): \Iterator
    {
        return new \ArrayIterator($this->getFields());
    }

    /**
     * Pack entity fields data into plain array.
     *
     * @return array
     *
     * @throws AccessorExceptionInterface
     */
    public function packFields(): array
    {
        $result = [];
        foreach ($this->fields as $field => $value) {
            if ($value instanceof AccessorInterface) {
                $result[$field] = $value->packValue();
            } else {
                $result[$field] = $value;
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * Include every composition public data into result.
     */
    public function publicFields(): array
    {
        $result = [];

        foreach ($this->getKeys() as $field) {
            if (!$this->isPublic($field)) {
                //We might need to use isset in future, for performance, for science
                continue;
            }

            $value = $this->getField($field);

            if ($value instanceof PublishableInterface) {
                $result[$field] = $value->publicFields();
            } else {
                $result[$field] = $value;
            }
        }

        return $result;
    }

    /**
     * Alias for packFields.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->packFields();
    }

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
        $this->flushFields();
    }

    /**
     * Reset every field value.
     */
    protected function flushFields()
    {
        $this->fields = [];
    }

    /**
     * Indication that field in public and can be presented in published data.
     *
     * @param string $field
     *
     * @return bool
     */
    abstract protected function isPublic(string $field): bool;

    /**
     * Check if field is fillable.
     *
     * @param string $field
     *
     * @return bool
     */
    abstract protected function isFillable(string $field): bool;

    /**
     * Get mutator associated with given field.
     *
     * @param string $field
     * @param string $type See MUTATOR_* constants
     *
     * @return mixed
     */
    abstract protected function getMutator(string $field, string $type);

    /**
     * Nullable fields would not require automatic accessor creation.
     *
     * @param string $field
     *
     * @return bool
     */
    protected function isNullable(string $field): bool
    {
        return false;
    }

    /**
     * Create instance of field accessor.
     *
     * @param mixed|string $accessor Might be entity implementation specific.
     * @param string       $field
     * @param mixed        $value
     * @param array        $context  Custom accessor context.
     *
     * @return AccessorInterface|null
     *
     * @throws AccessorExceptionInterface
     * @throws EntityException
     */
    protected function createAccessor(
        $accessor,
        string $field,
        $value,
        array $context = []
    ): AccessorInterface {
        if (!is_string($accessor) || !class_exists($accessor)) {
            throw new EntityException(
                "Unable to create accessor for field {$field} in " . static::class
            );
        }

        //Field as a context
        return new $accessor($value, $context + ['field' => $field, 'entity' => $this]);
    }
}