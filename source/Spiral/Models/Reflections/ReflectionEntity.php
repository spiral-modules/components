<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
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
        return $this->getMutators()['setter'];
    }

    /**
     * @return array
     */
    public function getGetters()
    {
        return $this->getMutators()['getter'];
    }

    /**
     * @return array
     */
    public function getAccessors()
    {
        return $this->getMutators()['accessor'];
    }

    /**
     * {@inheritdoc}
     *
     * @param bool $local Exclude parent methods.
     * @return \ReflectionMethod[]
     */
    public function getMethods($local = true)
    {
        $methods = [];

        foreach ($this->getMethods() as $method)
        {
            if ($local && $method->getDeclaringClass()->getName() != $this->getName())
            {
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
     * Must return instance of ReflectionEntity or null if no parent found.
     *
     * @return self|null
     */
    abstract protected function parentSchema();

    /**
     * Model mutators grouped by their type.
     *
     * @return array
     */
    protected function getMutators()
    {
        $mutators = [
            'getter'   => [],
            'setter'   => [],
            'accessor' => []
        ];

        foreach ($this->property('getters', true) as $field => $filter)
        {
            $mutators['getter'][$field] = $filter;
        }

        foreach ($this->property('setters', true) as $field => $filter)
        {
            $mutators['setter'][$field] = $filter;
        }

        foreach ($this->property('accessors', true) as $field => $filter)
        {
            $mutators['accessor'][$field] = $filter;
        }

        return $mutators;
    }

    /**
     * Read default model property value, will read "protected" and "private" properties.
     *
     * @param string $property Property name.
     * @param bool   $merge    If true value will be merged with all parent declarations.
     * @return mixed
     */
    final protected function property($property, $merge = false)
    {
        if (isset($this->cache[$property]))
        {
            return $this->cache[$property];
        }

        $defaults = $this->getDefaultProperties();
        if (isset($defaults[$property]))
        {
            $value = $defaults[$property];
        }
        else
        {
            return null;
        }

        if ($merge && ($this->getParentClass()->getName() != static::BASE_CLASS))
        {
            if (is_array($value))
            {
                $parent = $this->parentSchema($this->getParentClass()->getName());
                if (!empty($parent))
                {
                    $value = array_merge($parent->property($property, $merge), $value);
                }
            }
        }

        //To let traits apply schema changes
        return $this->cache[$property] = call_user_func(
            [$this->getName(), 'describeProperty'],
            $property, $value, $this
        );
    }
}