<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\ODM\Entities;

use Spiral\ODM\Configs\ODMConfig;
use Spiral\ODM\Document;
use Spiral\ODM\DocumentEntity;
use Spiral\ODM\Entities\Schemas\CollectionSchema;
use Spiral\ODM\Entities\Schemas\DocumentSchema;
use Spiral\ODM\Exceptions\DefinitionException;
use Spiral\ODM\Exceptions\SchemaException;
use Spiral\ODM\ODM;
use Spiral\ODM\ODMInterface;
use Spiral\Tokenizer\ClassLocatorInterface;

/**
 * Schema builder responsible for static analysis of existed Documents, their schemas, validations,
 * requested indexes and etc.
 */
class SchemaBuilder
{
    /**
     * @var DocumentSchema[]
     */
    private $documents = [];

    /**
     * @var CollectionSchema[]
     */
    private $collections = [];

    /**
     * @invisible
     *
     * @var ODMInterface
     */
    private $odm = null;

    /**
     * @var ODMConfig
     */
    private $config = null;

    /**
     * @param ODMInterface          $odm
     * @param ODMConfig             $config
     * @param ClassLocatorInterface $locator
     */
    public function __construct(
        ODMInterface $odm,
        ODMConfig $config,
        ClassLocatorInterface $locator
    ) {
        $this->odm = $odm;
        $this->config = $config;


        $this->locateDocuments($locator)->locateSources($locator);
        $this->describeCollections();
    }

    /**
     * @return ODMInterface
     */
    public function getODM()
    {
        return $this->odm;
    }

    /**
     * Resolve database alias.
     *
     * @param string $database
     *
     * @return string
     */
    public function databaseAlias($database)
    {
        if (empty($database)) {
            $database = $this->config->defaultDatabase();
        }

        //Spiral support ability to link multiple virtual databases together using aliases
        return $this->config->resolveAlias($database);
    }

    /**
     * Check if Document class known to schema builder.
     *
     * @param string $class
     *
     * @return bool
     */
    public function hasDocument($class)
    {
        return isset($this->documents[$class]);
    }

    /**
     * Instance of DocumentSchema associated with given class name.
     *
     * @param string $class
     *
     * @return DocumentSchema
     *
     * @throws SchemaException
     */
    public function document($class)
    {
        if ($class == DocumentEntity::class || $class == Document::class) {
            //No need to remember schema for abstract Document
            return new DocumentSchema(DocumentEntity::class, $this);
        }

        if (!isset($this->documents[$class])) {
            throw new SchemaException("Unknown document class '{$class}'");
        }

        return $this->documents[$class];
    }

    /**
     * @return DocumentSchema[]
     */
    public function getDocuments()
    {
        return $this->documents;
    }

    /**
     * @return CollectionSchema[]
     */
    public function getCollections()
    {
        return $this->collections;
    }

    /**
     * Create every requested collection index.
     *
     * @throws \MongoException
     */
    public function createIndexes()
    {
        foreach ($this->getCollections() as $collection) {
            if (empty($indexes = $collection->getIndexes())) {
                continue;
            }

            $odmCollection = $this->odm->database(
                $collection->getDatabase()
            )->selectCollection(
                $collection->getName()
            );

            foreach ($indexes as $index) {
                $options = [];
                if (isset($index['@options'])) {
                    $options = $index['@options'];
                    unset($index['@options']);
                }

                $odmCollection->createIndex($index, $options);
            }
        }
    }

    /**
     * Normalize document schema in lighter structure to be saved in ODM component memory.
     *
     * @return array
     *
     * @throws SchemaException
     * @throws DefinitionException
     */
    public function normalizeSchema()
    {
        $result = [];

        //Pre-packing collections
        foreach ($this->getCollections() as $collection) {
            $name = $collection->getDatabase() . '/' . $collection->getName();
            $result[$name] = $collection->getParent()->getName();
        }

        foreach ($this->getDocuments() as $document) {
            if ($document->isAbstract()) {
                continue;
            }

            $schema = [
                ODMInterface::D_DEFINITION   => $this->packDefinition($document->classDefinition()),
                ODMInterface::D_SOURCE       => $document->getSource(),
                ODMInterface::D_HIDDEN       => $document->getHidden(),
                ODMInterface::D_SECURED      => $document->getSecured(),
                ODMInterface::D_FILLABLE     => $document->getFillable(),
                ODMInterface::D_MUTATORS     => $document->getMutators(),
                ODMInterface::D_DEFAULTS     => $document->getDefaults(),
                ODMInterface::D_AGGREGATIONS => $this->packAggregations($document->getAggregations()),
                ODMInterface::D_COMPOSITIONS => array_keys($document->getCompositions()),
            ];

            if (!$document->isEmbeddable()) {
                $schema[ODMInterface::D_COLLECTION] = $document->getCollection();
                $schema[ODMInterface::D_DB] = $document->getDatabase();
            }

            ksort($schema);
            $result[$document->getName()] = $schema;
        }

        return $result;
    }

    /**
     * Get all mutators associated with field type.
     *
     * @param string $type Field type.
     *
     * @return array
     */
    public function getMutators($type)
    {
        return $this->config->getMutators($type);
    }

    /**
     * Get mutator alias if presented. Aliases used to simplify schema definition.
     *
     * @param string $alias
     *
     * @return string|array
     */
    public function mutatorAlias($alias)
    {
        return $this->config->mutatorAlias($alias);
    }

    /**
     * Locate every available Document class.
     *
     * @param ClassLocatorInterface $locator
     *
     * @return $this
     */
    protected function locateDocuments(ClassLocatorInterface $locator)
    {
        foreach ($locator->getClasses(DocumentEntity::class) as $class => $definition) {
            if ($class == DocumentEntity::class || $class == Document::class) {
                continue;
            }

            $this->documents[$class] = new DocumentSchema($class, $this);
        }

        return $this;
    }

    /**
     * Locate ORM entities sources.
     *
     * @param ClassLocatorInterface $locator
     *
     * @return $this
     */
    protected function locateSources(ClassLocatorInterface $locator)
    {
        foreach ($locator->getClasses(DocumentSource::class) as $class => $definition) {
            $reflection = new \ReflectionClass($class);

            if (
                $reflection->isAbstract()
                || empty($document = $reflection->getConstant('DOCUMENT'))
            ) {
                continue;
            }

            if ($this->hasDocument($document)) {
                //Associating source with record
                $this->document($document)->setSource($class);
            }
        }

        return $this;
    }

    /**
     * Create instances of CollectionSchema associated with found DocumentSchema instances.
     *
     * @throws SchemaException
     */
    protected function describeCollections()
    {
        foreach ($this->getDocuments() as $document) {
            if ($document->isEmbeddable()) {
                //Skip embedded models
                continue;
            }

            //Getting fully specified collection name (with specified db)
            $collection = $document->getDatabase() . '/' . $document->getCollection();
            if (isset($this->collections[$collection])) {
                //Already described by parent
                continue;
            }

            //Collection must be described by parent Document
            $parent = $document->getParent(true);
            $this->collections[$collection] = new CollectionSchema($parent);
        }
    }

    /**
     * Pack (normalize) class definition.
     *
     * @param mixed $definition
     *
     * @return array|string
     */
    private function packDefinition($definition)
    {
        if (is_string($definition)) {
            //Single collection class
            return $definition;
        }

        return [
            ODM::DEFINITION         => $definition['type'],
            ODM::DEFINITION_OPTIONS => $definition['options'],
        ];
    }

    /**
     * Pack (normalize) document aggregations.
     *
     * @param array $aggregations
     *
     * @return array
     */
    private function packAggregations(array $aggregations)
    {
        $result = [];
        foreach ($aggregations as $name => $aggregation) {
            $result[$name] = [
                ODM::AGR_TYPE  => $aggregation['type'],
                ODM::ARG_CLASS => $aggregation['class'],
                ODM::AGR_QUERY => $aggregation['query'],
            ];
        }

        return $result;
    }
}