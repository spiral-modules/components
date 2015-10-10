<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright �2009-2015
 */
namespace Spiral\ODM\Entities\Schemas;

use Doctrine\Common\Inflector\Inflector;
use Spiral\Models\Reflections\ReflectionEntity;
use Spiral\ODM\AtomicAccessorInterface;
use Spiral\ODM\Document;
use Spiral\ODM\Entities\Compositor;
use Spiral\ODM\Entities\SchemaBuilder;
use Spiral\ODM\Exceptions\DefinitionException;
use Spiral\ODM\Exceptions\SchemaException;
use Spiral\ODM\ODM;

/**
 * Performs analysis and schema building for one specific Document class.
 */
class DocumentSchema extends ReflectionEntity
{
    /**
     * Required to validly merge parent and children attributes.
     */
    const BASE_CLASS = Document::class;

    /**
     * @invisible
     * @var SchemaBuilder
     */
    protected $builder = null;

    /**
     * @param SchemaBuilder $builder Parent ODM schema (all other documents).
     * @param string        $class   Class name.
     * @throws \ReflectionException
     */
    public function __construct(SchemaBuilder $builder, $class)
    {
        $this->builder = $builder;
        parent::__construct($class);
    }

    /**
     * Document has not collection and only be embedded.
     *
     * @return bool
     */
    public function isEmbeddable()
    {
        return !$this->isSubclassOf(Document::class);
    }

    /**
     * Collection name associated with document model. Can automatically generate collection name
     * based on model class.
     *
     * @return mixed
     */
    public function getCollection()
    {
        if ($this->isEmbeddable()) {
            return null;
        }

        $collection = $this->property('collection');

        if (empty($collection)) {
            if ($this->parentSchema()) {
                //Using parent collection
                return $this->parentSchema()->getCollection();
            }

            $collection = Inflector::camelize($this->getShortName());
            $collection = Inflector::pluralize($collection);
        }

        return $collection;
    }

    /**
     * Database document data should be stored in. Database alias will be resolved.
     *
     * @return mixed
     */
    public function getDatabase()
    {
        if ($this->isEmbeddable()) {
            return null;
        }

        $database = $this->property('database');
        if (empty($database)) {
            if ($this->parentSchema()) {
                //Using parent database
                return $this->parentSchema()->getDatabase();
            }

            $database = $this->builder->getODM()->config()['default'];
        }

        $aliases = $this->builder->getODM()->config()['aliases'];
        while (isset($aliases[$database])) {
            $database = $aliases[$database];
        }

        return $database;
    }

    /**
     * {@inheritdoc}
     *
     * @throws SchemaException
     */
    public function getFields()
    {
        //We should select only embedded fields, no aggregations
        $fields = [];
        foreach ($this->getSchema() as $field => $type) {
            if ($this->isAggregation($type)) {
                //Aggregation
                continue;
            }

            if (is_array($type) && empty($type[0])) {
                throw new SchemaException("Type definition of {$this}.{$field} is invalid.");
            }

            $fields[$field] = $type;
        }

        return $fields;
    }

    /**
     * Document default values type-casted with model accessors, setters and compositors.
     *
     * @return array
     * @throws SchemaException
     */
    public function getDefaults()
    {
        //Default values described in defaults property, inherited
        $defaults = $this->property('defaults', true);

        $setters = $this->getSetters();
        $accessors = $this->getAccessors();

        foreach ($this->getFields() as $field => $type) {
            $default = is_array($type) ? [] : null;

            if (array_key_exists($field, $defaults)) {
                //Default value declared in model schema
                $default = $defaults[$field];
            }

            if (isset($setters[$field])) {
                try {
                    $setter = $setters[$field];

                    //Applying filter to default value
                    $default = call_user_func($setter, $default);
                } catch (\ErrorException $exception) {
                    //Ignoring
                }
            }

            if (isset($accessors[$field])) {
                $default = $this->accessorDefaults($accessors[$field], $type, $default);
            }

            //Using composition to resolve default value
            if (!empty($this->getCompositions()[$field])) {
                $default = $this->compositionDefaults($field, $default);
            }

            $defaults[$field] = $default;
        }

        return $defaults;
    }

    /**
     * Get indexes requested by document and it's children from the same collection.
     *
     * @return array
     */
    public function getIndexes()
    {
        if ($this->isEmbeddable()) {
            return [];
        }

        $indexes = $this->property('indexes', true);
        foreach ($this->getChildren(true) as $children) {
            $indexes = array_merge($indexes, $children->getIndexes());
        }

        return $indexes;
    }

    /**
     * Declared document compositions.
     *
     * @return array
     * @throws SchemaException
     */
    public function getCompositions()
    {
        $compositions = [];
        foreach ($this->getFields() as $field => $type) {
            if (is_scalar($type)) {
                if ($this->builder->hasDocument($type)) {
                    $compositions[$field] = [
                        'type'  => ODM::CMP_ONE,
                        'class' => $type
                    ];
                }

                continue;
            }

            $type = $type[0];
            if ($this->builder->hasDocument($type)) {
                $compositions[$field] = [
                    'type'  => ODM::CMP_MANY,
                    'class' => $type
                ];
            }
        }

        return $compositions;
    }

    /**
     * Declared document aggregations.
     *
     * @return array
     * @throws SchemaException
     */
    public function getAggregations()
    {
        $aggregations = [];
        foreach ($this->getSchema() as $field => $options) {
            if (!$this->isAggregation($options)) {
                //Not aggregation
                continue;
            }

            //Class to be aggregated
            $class = isset($options[Document::MANY])
                ? $options[Document::MANY]
                : $options[Document::ONE];

            if (!$this->builder->hasDocument($class)) {
                throw new SchemaException(
                    "Unable to build aggregation {$this}.{$field}, no such document '{$class}'."
                );
            }

            $document = $this->builder->document($class);
            if ($document->isEmbeddable()) {
                throw new SchemaException(
                    "Unable to build aggregation {$this}.{$field}, "
                    . "document '{$class}' does not have any collection."
                );
            }

            if (!empty($options[Document::MANY])) {
                //Aggregation may select parent document
                $class = $document->getParent(true)->getName();
            }

            $aggregations[$field] = [
                'type'       => isset($options[Document::ONE]) ? Document::ONE : Document::MANY,
                'class'      => $class,
                'collection' => $document->getCollection(),
                'database'   => $document->getDatabase(),
                'query'      => array_pop($options)
            ];
        }

        return $aggregations;
    }

    /**
     * {@inheritdoc}
     *
     * Schema can generate accessors and filters based on field type.
     */
    public function getMutators()
    {
        $mutators = parent::getMutators();

        //Trying to resolve mutators based on field type
        foreach ($this->getFields() as $field => $type) {
            //Resolved filters
            $resolved = [];

            if (
                is_array($type)
                && is_scalar($type[0])
                && $filter = $this->builder->getMutators('array::' . $type[0])
            ) {
                //Mutator associated to array with specified type
                $resolved += $filter;
            } elseif (is_array($type) && $filter = $this->builder->getMutators('array')) {
                //Default array mutator
                $resolved += $filter;
            } elseif (!is_array($type) && $filter = $this->builder->getMutators($type)) {
                //Mutator associated with type directly
                $resolved += $filter;
            }

            if (isset($resolved[self::MUTATOR_ACCESSOR])) {
                //Accessor options include field type, this is ODM specific behaviour
                $resolved[self::MUTATOR_ACCESSOR] = [
                    $resolved[self::MUTATOR_ACCESSOR],
                    is_array($type) ? $type[0] : $type
                ];
            }

            //Merging mutators and default mutators
            foreach ($resolved as $mutator => $filter) {
                if (!array_key_exists($field, $mutators[$mutator])) {
                    $mutators[$mutator][$field] = $filter;
                }
            }
        }

        //Some mutators may be described using aliases (for shortness)
        $mutators = $this->normalizeMutators($mutators);

        //Every composition is counted as field accessor :)
        foreach ($this->getCompositions() as $field => $composition) {
            $mutators[self::MUTATOR_ACCESSOR][$field] = [
                $composition['type'] == ODM::CMP_MANY ? Compositor::class : ODM::CMP_ONE,
                $composition['class']
            ];
        }

        return $mutators;
    }

    /**
     * Get Document child classes.
     *
     * Example:
     * Class A
     * Class B extends A
     * Class D extends A
     * Class E extends D
     *
     * Result: B, D, E
     *
     * @see getPrimary()
     * @param bool $sameCollection Find only children related to same collection as parent.
     * @param bool $firstOrder     Only child extended directly from current document.
     * @return DocumentSchema[]
     */
    public function getChildren($sameCollection = false, $firstOrder = false)
    {
        $result = [];
        foreach ($this->builder->getDocuments() as $document) {
            if ($document->isSubclassOf($this)) {
                if ($sameCollection && !$this->compareCollection($document)) {
                    //Child changed collection or database
                    continue;
                }

                if ($firstOrder && $document->getParentClass()->getName() != $this->getName()) {
                    //Grandson
                    continue;
                }

                $result[] = $document;
            }
        }

        return $result;
    }

    /**
     * Get schema of top parent of current document or document schema itself. This method is
     * reverse implementation of getChildren().
     *
     * @see getChindren()
     * @param bool $sameCollection Only document with same collection.
     * @return DocumentSchema
     */
    public function getParent($sameCollection = false)
    {
        $result = $this;
        foreach ($this->builder->getDocuments() as $document) {
            if (!$result->isSubclassOf($document)) {
                //I'm not your father!
                continue;
            }

            if ($sameCollection && !$result->compareCollection($document)) {
                //Different collection
                continue;
            }

            //Level down
            $result = $document;
        }

        return $result;
    }

    /**
     * Parent document schema or null. Similar to getParentClass().
     *
     * @see parentSchema()
     * @return DocumentSchema|null
     */
    public function getParentDocument()
    {
        return $this->parentSchema();
    }

    /**
     * Compile information required to resolve class instance using given set of fields. Fields
     * based definition will analyze unique fields in every child model to create association
     * between model class and required set of fields. Only document from same collection will be
     * involved in definition creation. Definition built only for child of first order.
     *
     * @return array|string
     * @throws SchemaException
     * @throws DefinitionException
     */
    public function classDefinition()
    {
        if (empty($children = $this->getChildren(true, true))) {
            //No children
            return $this->getName();
        }

        if ($this->getConstant('DEFINITION') == Document::DEFINITION_LOGICAL) {
            //Class definition will be performed using method with custom logic
            return [
                'type'    => Document::DEFINITION_LOGICAL,
                'options' => [$this->getName(), 'defineClass']
            ];
        }

        //Definition will be performed using field = class association
        $definition = [
            'type'    => Document::DEFINITION_FIELDS,
            'options' => []
        ];

        //We must sort child in order or unique fields
        uasort($children, [$this, 'sortChildren']);

        //Fields which are common for parent and child models
        $commonFields = $this->getFields();

        foreach ($children as $document) {
            //Child document fields
            if (empty($fields = $document->getFields())) {
                throw new DefinitionException(
                    "Child document {$document} of {$this} does not have any fields."
                );
            }

            $uniqueField = null;
            if (empty($commonFields)) {
                //Parent did not declare any fields, happen sometimes
                $commonFields = $fields;
                $uniqueField = key($fields);
            } else {
                foreach ($fields as $field => $type) {
                    if (!isset($commonFields[$field])) {
                        if (empty($uniqueField)) {
                            $uniqueField = $field;
                        }

                        //New non unique field (must be excluded from analysis)
                        $commonFields[$field] = true;
                    }
                }
            }

            if (empty($uniqueField)) {
                throw new DefinitionException(
                    "Child document {$document} of {$this} does not have any unique field."
                );
            }

            $definition['options'][$uniqueField] = $document->getName();
        }

        return $definition;
    }

    /**
     * Get declared document schema (merged with parent entity(s) values).
     *
     * @return array
     */
    protected function getSchema()
    {
        //Reading schema as property to inherit all values
        return $this->property('schema', true);
    }

    /**
     * {@inheritdoc}
     */
    protected function parentSchema()
    {
        if (!$this->builder->hasDocument($this->getParentClass()->getName())) {
            return null;
        }

        return $this->builder->document($this->getParentClass()->getName());
    }

    /**
     * Check if both documents belongs to same collection. Documents without declared collection
     * must be counted as documents from same collection.
     *
     * @param DocumentSchema $document
     * @return bool
     */
    protected function compareCollection(DocumentSchema $document)
    {
        return $document->getCollection() == $this->getCollection()
        && $document->getDatabase() == $this->getDatabase();
    }

    /**
     * Check if type definition describes aggregation.
     *
     * @param mixed $type
     * @return bool
     */
    private function isAggregation($type)
    {
        return is_array($type) && (
            array_key_exists(Document::MANY, $type)
            || array_key_exists(Document::ONE, $type)
        );
    }

    /**
     * Sort child documents in order or declared fields.
     *
     * @param DocumentSchema $childA
     * @param DocumentSchema $childB
     * @return int
     */
    private function sortChildren(DocumentSchema $childA, DocumentSchema $childB)
    {
        return count($childA->getFields()) > count($childB->getFields());
    }

    /**
     * Cast default value using accessor.
     *
     * @param string $accessor
     * @param mixed  $type
     * @param mixed  $default
     * @return mixed
     */
    private function accessorDefaults($accessor, $type, $default)
    {
        $options = is_array($type) ? $type[0] : $type;
        if (is_array($accessor)) {
            list($accessor, $options) = $accessor;
        }

        if ($accessor != ODM::CMP_ONE) {
            //Not an accessor but composited class
            $accessor = new $accessor($default, null, $this->builder->getODM(), $options);

            if ($accessor instanceof AtomicAccessorInterface) {
                return $accessor->defaultValue();
            }
        }

        return $default;
    }

    /**
     * Get default value for composition.
     *
     * @param string $field
     * @param mixed  $default Casted default value.
     * @return mixed
     */
    private function compositionDefaults($field, $default)
    {
        $composition = $this->getCompositions()[$field];
        if ($composition['type'] == ODM::CMP_MANY) {
            if (!empty($default) && $default !== []) {
                throw new SchemaException(
                    "Default value of {$this}.{$field} is not compatible with document composition."
                );
            }

            return [];
        }

        if (empty($default)) {
            return $this->builder->document($composition['class'])->getDefaults();
        }

        return $default;
    }

    /**
     * Resolve mutator aliases and normalize accessor definitions.
     *
     * @param array $mutators
     * @return array
     */
    private function normalizeMutators(array $mutators)
    {
        foreach ($mutators as $mutator => &$filters) {
            foreach ($filters as $field => $filter) {
                $filters[$field] = $this->builder->mutatorAlias($filter);

                if ($mutator == self::MUTATOR_ACCESSOR && is_string($filters[$field])) {

                    $type = null;
                    if (!empty($this->getFields()[$field])) {
                        $type = $this->getFields()[$field];
                    }

                    $filters[$field] = [$filters[$field], is_array($type) ? $type[0] : $type];
                }
            }
            unset($filters);
        }

        return $mutators;
    }
}