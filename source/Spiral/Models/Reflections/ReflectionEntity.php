<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Models\Reflections;

use Spiral\Models\DataEntity;

/**
 * Reflection associated with one specific DataEntity class.
 */
abstract class ReflectionEntity extends \ReflectionClass
{
    /**
     * Required to validly merge parent and children attributes.
     */
    const BASE_CLASS = DataEntity::class;

    /**
     * Mutator names.
     */
    const MUTATOR_SETTER   = 'setter';
    const MUTATOR_GETTER   = 'getter';
    const MUTATOR_ACCESSOR = 'accessor';

    /**
     * Properties cache.
     *
     * @invisible
     * @var array
     */
    private $cache = [];

    /**
     * @return array
     */
    public function getSecured()
    {
        if ($this->property('secured', true) === '*') {
            return $this->property('secured', true);
        }

        return array_unique($this->property('secured', true));
    }

    /**
     * @return array
     */
    public function getFillable()
    {
        return array_unique($this->property('fillable', true));
    }

    /**
     * @return array
     */
    public function getHidden()
    {
        return array_unique($this->property('hidden', true));
    }

    /**
     * @return array
     */
    public function getValidates()
    {
        return $this->property('validates', true);
    }

    /**
     * @return array
     */
    public function getSetters()
    {
        return $this->getMutators()[self::MUTATOR_SETTER];
    }

    /**
     * @return array
     */
    public function getGetters()
    {
        return $this->getMutators()[self::MUTATOR_GETTER];
    }

    /**
     * @return array
     */
    public function getAccessors()
    {
        return $this->getMutators()[self::MUTATOR_ACCESSOR];
    }

    /**
     * Get methods declared in current class and exclude methods declared in parents.
     *
     * @return \ReflectionMethod[]
     */
    public function getLocalMethods()
    {
        $methods = [];
        foreach ($this->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() != $this->getName()) {
                continue;
            }

            $methods[] = $method;
        }

        return $methods;
    }

    /**
     * Fields associated with their type.
     *
     * @return array
     */
    abstract public function getFields();

    /**
     * Model mutators grouped by their type.
     *
     * @return array
     */
    public function getMutators()
    {
        $mutators = [
            self::MUTATOR_GETTER   => [],
            self::MUTATOR_SETTER   => [],
            self::MUTATOR_ACCESSOR => []
        ];

        foreach ($this->property('getters', true) as $field => $filter) {
            $mutators[self::MUTATOR_GETTER][$field] = $filter;
        }

        foreach ($this->property('setters', true) as $field => $filter) {
            $mutators[self::MUTATOR_SETTER][$field] = $filter;
        }

        foreach ($this->property('accessors', true) as $field => $filter) {
            $mutators[self::MUTATOR_ACCESSOR][$field] = $filter;
        }

        return $mutators;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getName();
    }

    /**
     * Must return instance of ReflectionEntity or null if no parent found.
     *
     * @return self|null
     */
    abstract protected function parentSchema();

    /**
     * Read default model property value, will read "protected" and "private" properties. Method
     * raises entity event "describe" to allow it traits modify needed values.
     *
     * @param string $property Property name.
     * @param bool   $merge    If true value will be merged with all parent declarations.
     * @return mixed
     */
    final protected function property($property, $merge = false)
    {
        if (isset($this->cache[$property])) {
            //Property merging and trait events are pretty slow
            return $this->cache[$property];
        }

        $properties = $this->getDefaultProperties();
        if (isset($properties[$property])) {
            $value = $properties[$property];
        } else {
            return null;
        }

        //Merge with parent value requested
        if ($merge && ($this->getParentClass()->getName() != static::BASE_CLASS)) {

            //For the reasons we can merge only arrays
            if (is_array($value) && !empty($parent = $this->parentSchema())) {
                $value = array_merge($parent->property($property, $merge), $value);
            }
        }

        //To let traits apply schema changes
        return $this->cache[$property] = call_user_func(
            [$this->getName(), 'describeProperty'], $this, $property, $value
        );
    }
}