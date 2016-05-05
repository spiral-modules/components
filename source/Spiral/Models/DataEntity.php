<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Models;

use Spiral\Models\Exceptions\EntityException;
use Spiral\Models\Traits\ValidatorTrait;
use Spiral\Validation\ValidatesInterface;
use Spiral\Validation\ValidatorInterface;

/**
 * DataEntity in spiral used to represent basic data set with validation rules, filters and
 * accessors. Most of spiral models (ORM and ODM, HttpFilters) will extend data entity. In addition
 * it creates magic set of getters and setters for every field name (see validator trait) in model.
 *
 * DataEntity provides ability to configure it's state using internal properties.
 */
class DataEntity extends AbstractEntity implements ValidatesInterface
{
    use ValidatorTrait;

    /**
     * List of fields must be hidden from publicFields() method.
     *
     * @see publicFields()
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * Set of fields allowed to be filled using setFields() method.
     *
     * @see setFields()
     *
     * @var array
     */
    protected $fillable = [];

    /**
     * List of fields not allowed to be filled by setFields() method. Replace with and empty array
     * to allow all fields.
     *
     * By default all entity fields are settable! Opposite behaviour has to be described in entity
     * child implementations.
     *
     * @see setFields()
     *
     * @var array|string
     */
    protected $secured = [];

    /**
     * @see setField()
     *
     * @var array
     */
    protected $setters = [];

    /**
     * @see getField()
     *
     * @var array
     */
    protected $getters = [];

    /**
     * Accessor used to mock field data and filter every request thought itself.
     *
     * @see getField()
     * @see setField()
     *
     * @var array
     */
    protected $accessors = [];

    /**
     * Validation rules in a form supported by active validator binding.
     *
     * @var array
     */
    protected $validates = [];

    /**
     * {@inheritdoc}
     *
     * Include every composition public data into result.
     */
    public function publicFields()
    {
        $result = [];

        foreach ($this->getKeys() as $field => $value) {
            if (in_array($field, $this->hidden)) {
                //We might need to use isset in future, for performance
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
     * {@inheritdoc}
     */
    public function setField($name, $value, $filter = true)
    {
        parent::setField($name, $value, $filter);
        $this->invalidate(false);
    }

    /**
     * Entity specific validator (if any).
     *
     * @return ValidatorInterface|null
     */
    protected function createValidator()
    {
        if (empty($this->validates)) {
            return null;
        }

        return $this->intiaiteValidator($this->validates);
    }

    /**
     * Check if field can be set using setFields() method.
     *
     * @see   setField()
     * @see   $fillable
     * @see   $secured
     *
     * @param string $field
     *
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
     *
     * @return mixed|null
     *
     * @throws EntityException
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
     * Destruct data entity.
     */
    public function __destruct()
    {
        parent::__destruct();
        $this->validator = null;
    }
}