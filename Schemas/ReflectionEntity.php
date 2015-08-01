<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Models\Schemas;

use Spiral\Models\DataEntity;

/**
 *
 */
abstract class ReflectionEntity extends \ReflectionClass
{
    const BASE_CLASS = DataEntity::class;

    /**
     * Cache to speed up schema building.
     *
     * @invisible
     * @var array
     */
    private $propertiesCache = [];

    /**
     * Document namespace. Both start and end namespace separators will be removed, to add start
     * separator (absolute) namespace use method parameter "absolute".
     *
     * @param bool $absolute \\ will be prepended to namespace if true, disabled by default.
     * @return string
     */
    public function getNamespace($absolute = false)
    {
        return ($absolute ? '\\' : '') . trim($this->getNamespaceName(), '\\');
    }

    /**
     * Read default model property value, will read "protected" and "private" properties.
     *
     * @param string $property Property name.
     * @param bool   $merge    If true value will be merged with all parent declarations.
     * @return mixed
     */
    abstract protected function property($property, $merge = false);

    abstract protected function parentSchema();

    /**
     * Getting all secured fields.
     *
     * @return array
     */
    public function getSecured()
    {
        return array_unique($this->property('secured', true));
    }

    /**
     * Getting all mass assignable fields.
     *
     * @return array
     */
    public function getFillable()
    {
        return array_unique($this->property('fillable', true));
    }

    /**
     * Getting all hidden fields.
     *
     * @return array
     */
    public function getHidden()
    {
        return array_unique($this->property('hidden', true));
    }

    /**
     * Get all entity validation rules (merged with parent model(s) values).
     *
     * @return array
     */
    public function getValidates()
    {
        return $this->property('validates', true);
    }

    /**
     * All methods declared in document. Method will include information about parameters, return
     * type, static declaration and access level.
     *
     * @return \ReflectionMethod[]
     */
    public function getMethods()
    {
        $methods = [];

        foreach ($this->reflection->getMethods() as $method)
        {
            if ($method->getDeclaringClass() != $this->reflection)
            {
                continue;
            }

            $methods[] = $method;
        }

        return $methods;
    }

    /**
     * Find all field mutators.
     *
     * @return mixed
     */
    public function getMutators()
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
     * Get document get filters (merged with parent model(s) values).
     *
     * @return array
     */
    public function getGetters()
    {
        return $this->getMutators()['getter'];
    }

    /**
     * Get document set filters (merged with parent model(s) values).
     *
     * @return array
     */
    public function getSetters()
    {
        return $this->getMutators()['setter'];
    }

    /**
     * Get document field accessors, this method will automatically create accessors for compositions.
     *
     * @return array
     */
    public function getAccessors()
    {
        return $this->getMutators()['accessor'];
    }

    /**
     * Fields associated with their type.
     *
     * @return array
     */
    abstract public function getFields();
}