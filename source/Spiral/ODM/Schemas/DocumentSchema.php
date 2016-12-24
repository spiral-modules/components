<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ODM\Schemas;

use Doctrine\Common\Inflector\Inflector;
use Spiral\Models\Reflections\ReflectionEntity;
use Spiral\ODM\Configs\MutatorsConfig;
use Spiral\ODM\Document;
use Spiral\ODM\DocumentEntity;
use Spiral\ODM\Entities\DocumentInstantiator;
use Spiral\ODM\Exceptions\SchemaException;
use Spiral\ODM\Schemas\Definitions\AggregationDefinition;
use Spiral\ODM\Schemas\Definitions\IndexDefinition;

class DocumentSchema implements SchemaInterface
{
    /**
     * @var ReflectionEntity
     */
    private $reflection;

    /**
     * @invisible
     *
     * @var MutatorsConfig
     */
    private $mutators;

    /**
     * @param ReflectionEntity $reflection
     * @param MutatorsConfig   $config
     */
    public function __construct(ReflectionEntity $reflection, MutatorsConfig $config)
    {
        $this->reflection = $reflection;
        $this->mutators = $config;
    }

    /**
     * @return string
     */
    public function getClass(): string
    {
        return $this->reflection->getName();
    }

    /**
     * @return ReflectionEntity
     */
    public function getReflection(): ReflectionEntity
    {
        return $this->reflection;
    }

    /**
     * @return string
     */
    public function getInstantiator(): string
    {
        return $this->reflection->getConstant('INSTANTIATOR') ?? DocumentInstantiator::class;
    }

    /**
     * {@inheritdoc}
     */
    public function isEmbedded(): bool
    {
        return !$this->reflection->isSubclassOf(Document::class)
            && $this->reflection->isSubclassOf(DocumentEntity::class);
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabase()
    {
        if ($this->isEmbedded()) {
            throw new SchemaException(
                "Unable to get database name for embedded model {$this->reflection}"
            );
        }

        $database = $this->reflection->getConstant('DATABASE');
        if (empty($database)) {
            //Empty database to be used
            return null;
        }

        return $database;
    }

    /**
     * {@inheritdoc}
     */
    public function getCollection(): string
    {
        if ($this->isEmbedded()) {
            throw new SchemaException(
                "Unable to get collection name for embedded model {$this->reflection}"
            );
        }

        $collection = $this->reflection->getConstant('COLLECTION');
        if (empty($collection)) {
            //Generate collection using short class name
            $collection = Inflector::camelize($this->reflection->getShortName());
            $collection = Inflector::pluralize($collection);
        }

        return $collection;
    }

    /**
     * Get every embedded entity field (excluding declarations of aggregations).
     *
     * @return array
     */
    public function getFields(): array
    {
        $fields = $this->reflection->getFields();

        foreach ($fields as $field => $type) {
            if ($this->isAggregation($type)) {
                unset($fields[$field]);
            }
        }

        return $fields;
    }

    /**
     * {@inheritdoc}
     */
    public function getIndexes(): array
    {
        if ($this->isEmbedded()) {
            throw new SchemaException(
                "Unable to get indexes for embedded model {$this->reflection}"
            );
        }

        $indexes = $this->reflection->getProperty('indexes', true);
        if (empty($indexes) || !is_array($indexes)) {
            return [];
        }

        $result = [];
        foreach ($indexes as $index) {
            $options = [];
            if (isset($index['@options'])) {
                $options = $index['@options'];
                unset($index['@options']);
            }

            $result[] = new IndexDefinition($index, $options);
        }

        return array_unique($result);
    }

    /**
     * @return AggregationDefinition[]
     */
    public function getAggregations(): array
    {
        $result = [];
        foreach ($this->reflection->getFields() as $field => $type) {
            if ($this->isAggregation($type)) {
                $aggregationType = isset($type[Document::ONE]) ? Document::ONE : Document::MANY;

                $result[$field] = new AggregationDefinition(
                    $aggregationType,        //Aggregation type
                    $type[$aggregationType], //Class name
                    array_pop($type)         //Query template
                );
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function resolvePrimary(SchemaBuilder $builder): string
    {
        //Let's define a way how to separate one model from another based on given fields
        $helper = new InheritanceHelper($this, $builder->getSchemas());

        return $helper->findPrimary();
    }

    /**
     * {@inheritdoc}
     */
    public function packSchema(SchemaBuilder $builder): array
    {
        return [
            //Instantion options and behaviour (if any)
            DocumentEntity::SH_INSTANTIATION => $this->instantiationOptions($builder),

            //Default entity state (builder is needed to resolve recursive defaults)
            DocumentEntity::SH_DEFAULTS      => $this->buildDefaults($builder),

            //Entity behaviour
            DocumentEntity::SH_HIDDEN        => $this->reflection->getHidden(),
            DocumentEntity::SH_SECURED       => $this->reflection->getSecured(),
            DocumentEntity::SH_FILLABLE      => $this->reflection->getFillable(),

            //Mutators can be altered based on ODM\SchemasConfig
            DocumentEntity::SH_MUTATORS      => $this->buildMutators(),

            //Document behaviours (we can mix them with accessors due potential inheritance)
            DocumentEntity::SH_COMPOSITIONS  => $this->buildCompositions($builder),
            DocumentEntity::SH_AGGREGATIONS  => $this->packAggregations($builder),
        ];
    }

    /**
     * Define instantiator specific options (usually needed to resolve class inheritance). Might
     * return null if associated instantiator is unknown to DocumentSchema.
     *
     * @param SchemaBuilder $builder
     *
     * @return mixed
     */
    protected function instantiationOptions(SchemaBuilder $builder)
    {
        if ($this->getInstantiator() != DocumentInstantiator::class) {
            //Unable to define options for non default inheritance based instantiator
            return null;
        }

        //Let's define a way how to separate one model from another based on given fields
        $helper = new InheritanceHelper($this, $builder->getSchemas());

        return $helper->makeDefinition();
    }

    /**
     * Entity default values.
     *
     * @param SchemaBuilder $builder
     *
     * @return array
     */
    protected function buildDefaults(SchemaBuilder $builder): array
    {
        //User defined default values
        $userDefined = $this->reflection->getProperty('defaults');

        //We need mutators to normalize default values
        $mutators = $this->buildMutators();

        $defaults = [];
        foreach ($this->getFields() as $field => $type) {
            $default = is_array($type) ? [] : null;

            if (array_key_exists($field, $userDefined)) {
                //No merge to keep fields order intact
                $default = $userDefined[$field];
            }

            if (array_key_exists($field, $defaults)) {
                //Default value declared in model schema
                $default = $defaults[$field];
            }

            //Let's process default value using associated setter
            if (isset($mutators[DocumentEntity::MUTATOR_SETTER][$field])) {
                try {
                    $setter = $mutators[DocumentEntity::MUTATOR_SETTER][$field];
                    $default = call_user_func($setter, $default);
                } catch (\Exception $exception) {
                    //Unable to generate default value, use null or empty array as fallback
                }
            }

            //todo: default accessors
            //if (isset($accessors[$field])) {
            //    $default = $this->accessorDefaults($accessors[$field], $type, $default);
            //}

            //todo: compositions
            //Using composition to resolve default value
            //if (!empty($this->getCompositions()[$field])) {
            //    $default = $this->compositionDefaults($field, $default);
            //}

            //Registering default values
            $defaults[$field] = $default;
        }

        return $defaults;
    }

    /**
     * Generate set of mutators associated with entity fields using user defined and automatic
     * mutators.
     *
     * @see MutatorsConfig
     * @return array
     */
    protected function buildMutators(): array
    {
        $mutators = $this->reflection->getMutators();

        //Trying to resolve mutators based on field type
        foreach ($this->getFields() as $field => $type) {
            //Resolved mutators
            $resolved = [];

            if (
                is_array($type)
                && is_scalar($type[0])
                && $filter = $this->mutators->getMutators('array::' . $type[0])
            ) {
                //Mutator associated to array with specified type
                $resolved += $filter;
            } elseif (is_array($type) && $filter = $this->mutators->getMutators('array')) {
                //Default array mutator
                $resolved += $filter;
            } elseif (!is_array($type) && $filter = $this->mutators->getMutators($type)) {
                //Mutator associated with type directly
                $resolved += $filter;
            }

            //Merging mutators and default mutators
            foreach ($resolved as $mutator => $filter) {
                if (!array_key_exists($field, $mutators[$mutator])) {
                    $mutators[$mutator][$field] = $filter;
                }
            }
        }

        //Some mutators may be described using aliases (for shortness)
//        $mutators = $this->normalizeMutators($mutators);
//
//        //Every composition is counted as field accessor :)
//        foreach ($this->getCompositions() as $field => $composition) {
//            $mutators[AbstractEntity::MUTATOR_ACCESSOR][$field] = [
//                $composition['type'] == ODM::CMP_MANY ? Compositor::class : ODM::CMP_ONE,
//                $composition['class'],
//            ];
//        }

        return $mutators;
    }

    public function buildCompositions(SchemaBuilder $builder): array
    {
        return [];
    }

    /**
     * Pack aggregations into simple array definition.
     *
     * @param SchemaBuilder $builder
     *
     * @return array
     *
     * @throws SchemaException
     */
    protected function packAggregations(SchemaBuilder $builder): array
    {
        $result = [];
        foreach ($this->getAggregations() as $name => $aggregation) {
            if (!$builder->hasSchema($aggregation->getClass())) {
                throw new SchemaException(
                    "Aggregation {$this->getClass()}.'{$name}' refers to undefined document '{$aggregation->getClass()}'"
                );
            }

            if ($builder->getSchema($aggregation->getClass())->isEmbedded()) {
                throw new SchemaException(
                    "Aggregation {$this->getClass()}.'{$name}' refers to non storable document '{$aggregation->getClass()}'"
                );
            }

            $result[$name] = $aggregation->packSchema();
        }

        return $result;
    }

    /**
     * Check if field schema/type defines aggregation.
     *
     * @param mixed $type
     *
     * @return bool
     */
    protected function isAggregation($type): bool
    {
        if (is_array($type)) {
            if (isset($type[Document::ONE]) || isset($type[Document::MANY])) {
                return true;
            }
        }

        return false;
    }
}