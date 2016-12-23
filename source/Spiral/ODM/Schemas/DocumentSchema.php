<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ODM\Schemas;

use Doctrine\Common\Inflector\Inflector;
use Spiral\Models\Reflections\ReflectionEntity;
use Spiral\ODM\Configs\SchemasConfig;
use Spiral\ODM\Document;
use Spiral\ODM\DocumentEntity;
use Spiral\ODM\Entities\DocumentInstantiator;
use Spiral\ODM\Exceptions\SchemaException;

class DocumentSchema implements SchemaInterface
{
    /**
     * @var SchemasConfig
     */
    private $config;

    /**
     * @var ReflectionEntity
     */
    private $reflection;

    /**
     * @param ReflectionEntity $reflection
     */
    public function __construct(ReflectionEntity $reflection)
    {
        $this->reflection = $reflection;
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
            //Let's generate collection automatically

            //todo: parent reference!!!!
//            if ($this->reflection->parentReflection()) {
//                //Using parent collection
//                return $this->parentSchema()->getCollection();
//            }

            //Generating collection using short class name
            $collection = Inflector::camelize($this->reflection->getShortName());
            $collection = Inflector::pluralize($collection);
        }

        return $collection;
    }

    /**
     * {@inheritdoc}
     */
    public function getIndexes(): array
    {
        //todo: create indexes (keep collections)
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function packSchema(SchemaBuilder $builder): array
    {
        return [
            //Instantion options and behaviour (if any)
            DocumentEntity::SH_INSTANTIATION => $this->instantiationOptions($builder),

            //Default entity state
            DocumentEntity::SH_DEFAULTS      => $this->getDefaults(),

            //Entity behaviour
            DocumentEntity::SH_HIDDEN        => $this->reflection->getHidden(),
            DocumentEntity::SH_SECURED       => $this->reflection->getSecured(),
            DocumentEntity::SH_FILLABLE      => $this->reflection->getFillable(),
            DocumentEntity::SH_MUTATORS      => [],

            //Document behaviours (we can mix them with accessors due potential inheritance)
            DocumentEntity::SH_COMPOSITIONS  => [],
            DocumentEntity::SH_AGGREGATIONS  => [],
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
        $helper = new InheritanceDefinition($this, $builder->getSchemas());

        return $helper->makeDefinition();
    }

    /**
     * Entity default values.
     *
     * @todo is it needed in a current form?
     * @return array
     */
    protected function getDefaults()
    {
        return $this->reflection->getFields();
    }

//    /**
//     * Get parent schema (if any).
//     *
//     * @return DocumentSchema
//     */
//    protected function parentSchema(): null
//    {
//    }
}