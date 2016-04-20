<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Models\Reflections;

use Spiral\Models\AbstractEntity;

/**
 * Provides ability to generate entity schema based on given entity class and default property
 * values, support value inheritance!
 *
 * @method string getName()
 * @method \ReflectionMethod[] getMethods()
 * @method \ReflectionClass|null getParentClass()
 */
class ReflectionEntity
{
    /**
     * Required to validly merge parent and children attributes.
     */
    const BASE_CLASS = AbstractEntity::class;

    /**
     * Properties cache.
     *
     * @invisible
     *
     * @var array
     */
    private $cache = [];

    /**
     * @var \ReflectionClass
     */
    private $reflection = null;

    /**
     * Only support SchematicEntity classes!
     *
     * @param string $class
     */
    public function __construct($class)
    {
        $this->reflection = new \ReflectionClass($class);
    }

    /**
     * @return \ReflectionClass
     */
    public function getReflection()
    {
        return $this->reflection;
    }

    /**
     * @return array
     */
    public function getSecured()
    {
        if ($this->getProperty('secured', true) === '*') {
            return $this->getProperty('secured', true);
        }

        return array_unique($this->getProperty('secured', true));
    }

    /**
     * @return array
     */
    public function getFillable()
    {
        return array_unique($this->getProperty('fillable', true));
    }

    /**
     * @return array
     */
    public function getHidden()
    {
        return array_unique($this->getProperty('hidden', true));
    }

    /**
     * @return array
     */
    public function getValidates()
    {
        return $this->getProperty('validates', true);
    }

    /**
     * @return array
     */
    public function getSetters()
    {
        return $this->getMutators()[AbstractEntity::MUTATOR_SETTER];
    }

    /**
     * @return array
     */
    public function getGetters()
    {
        return $this->getMutators()[AbstractEntity::MUTATOR_GETTER];
    }

    /**
     * @return array
     */
    public function getAccessors()
    {
        return $this->getMutators()[AbstractEntity::MUTATOR_ACCESSOR];
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
    public function getFields()
    {
        //Default property to store schema
        return (array)$this->getProperty('schema', true);
    }

    /**
     * Model mutators grouped by their type.
     *
     * @return array
     */
    public function getMutators()
    {
        $mutators = [
            AbstractEntity::MUTATOR_GETTER   => [],
            AbstractEntity::MUTATOR_SETTER   => [],
            AbstractEntity::MUTATOR_ACCESSOR => [],
        ];

        foreach ((array)$this->getProperty('getters', true) as $field => $filter) {
            $mutators[AbstractEntity::MUTATOR_GETTER][$field] = $filter;
        }

        foreach ((array)$this->getProperty('setters', true) as $field => $filter) {
            $mutators[AbstractEntity::MUTATOR_SETTER][$field] = $filter;
        }

        foreach ((array)$this->getProperty('accessors', true) as $field => $filter) {
            $mutators[AbstractEntity::MUTATOR_ACCESSOR][$field] = $filter;
        }

        return $mutators;
    }

    /**
     * Bypassing call to reflection.
     *
     * @param string $name
     * @param array  $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->reflection, $name], $arguments);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getName();
    }

    /**
     * Cloning and flushing cache.
     */
    public function __clone()
    {
        $this->cache = [];
    }

    /**
     * Read default model property value, will read "protected" and "private" properties. Method
     * raises entity event "describe" to allow it traits modify needed values.
     *
     * @param string $property Property name.
     * @param bool   $merge    If true value will be merged with all parent declarations.
     *
     * @return mixed
     */
    final protected function getProperty($property, $merge = false)
    {
        if (isset($this->cache[$property])) {
            //Property merging and trait events are pretty slow
            return $this->cache[$property];
        }

        $properties = $this->reflection->getDefaultProperties();

        if (isset($properties[$property])) {
            $value = $properties[$property];
        } else {
            return null;
        }

        //Merge with parent value requested
        if ($merge && ($this->getParentClass()->getName() != static::BASE_CLASS)) {

            if (is_array($value) && !empty($this->getParentClass())) {
                $parent = clone $this;
                $parent->reflection = $this->getParentClass();

                $parentValue = $parent->getProperty($property, $merge);

                if (is_array($parentValue)) {
                    $value = array_merge($parentValue, $value);
                }
            }
        }

        //To let traits apply schema changes
        return $this->cache[$property] = call_user_func(
            [$this->getName(), 'describeProperty'], $this, $property, $value
        );
    }
}