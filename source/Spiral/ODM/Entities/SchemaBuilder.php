<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ODM\Entities;

use Spiral\Core\Component;
use Spiral\Core\Traits\ConfigurableTrait;
use Spiral\ODM\Document;
use Spiral\ODM\Entities\Schemas\CollectionSchema;
use Spiral\ODM\Entities\Schemas\DocumentSchema;
use Spiral\ODM\Exceptions\DefinitionException;
use Spiral\ODM\Exceptions\SchemaException;
use Spiral\ODM\ODM;
use Spiral\Tokenizer\TokenizerInterface;

/**
 * Schema builder responsible for static analysis of existed Documents, their schemas, validations,
 * requested indexes and etc.
 */
class SchemaBuilder extends Component
{
    /**
     * Schema builder configuration includes mutators list and etc.
     */
    use ConfigurableTrait;

    /**
     * @var DocumentSchema[]
     */
    private $documents = [];

    /**
     * @var CollectionSchema[]
     */
    private $collections = [];

    /**
     * @var ODM
     */
    protected $odm = null;

    /**
     * @param ODM                $odm
     * @param array              $config
     * @param TokenizerInterface $tokenizer
     */
    public function __construct(ODM $odm, array $config, TokenizerInterface $tokenizer)
    {
        $this->config = $config;
        $this->odm = $odm;

        $this->locateDocuments($tokenizer);
        $this->describeCollections();
    }

    /**
     * @return ODM
     */
    public function getODM()
    {
        return $this->odm;
    }

    /**
     * Check if Document class known to schema builder.
     *
     * @param string $class
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
     * @return DocumentSchema
     * @throws SchemaException
     */
    public function document($class)
    {
        if ($class == Document::class) {
            //No need to remember schema for abstract Document
            return new DocumentSchema($this, Document::class);
        }

        if (!isset($this->documents[$class])) {
            throw new SchemaException("Unknown document class '{$class}'.");
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

            //We can safely create odm Collection here, as we not going to use functionality requires
            //finalized schema
            $odmCollection = $this->odm->db(
                $collection->getDatabase()
            )->odmCollection(
                $collection->getName()
            );

            foreach ($indexes as $index) {
                $options = [];
                if (isset($index[Document::INDEX_OPTIONS])) {
                    $options = $index[Document::INDEX_OPTIONS];
                    unset($index[Document::INDEX_OPTIONS]);
                }

                $odmCollection->ensureIndex($index, $options);
            }
        }
    }

    /**
     * Normalize document schema in lighter structure to be saved in ODM component memory.
     *
     * @return array
     * @throws SchemaException
     * @throws DefinitionException
     */
    public function normalizeSchema()
    {
        $result = [];
        foreach ($this->getCollections() as $collection) {
            $name = $collection->getDatabase() . '/' . $collection->getName();
            $result[$name] = $collection->getParent()->getName();
        }

        foreach ($this->getDocuments() as $document) {
            if ($document->isAbstract()) {
                continue;
            }

            $schema = [
                ODM::D_DEFINITION   => $this->packDefinition($document->classDefinition()),
                ODM::D_DEFAULTS     => $document->getDefaults(),
                ODM::D_HIDDEN       => $document->getHidden(),
                ODM::D_SECURED      => $document->getSecured(),
                ODM::D_FILLABLE     => $document->getFillable(),
                ODM::D_MUTATORS     => $document->getMutators(),
                ODM::D_VALIDATES    => $document->getValidates(),
                ODM::D_AGGREGATIONS => $this->packAggregations($document->getAggregations()),
                ODM::D_COMPOSITIONS => array_keys($document->getCompositions())
            ];

            if (!$document->isEmbeddable()) {
                $schema[ODM::D_COLLECTION] = $document->getCollection();
                $schema[ODM::D_DB] = $document->getDatabase();
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
     * @return array
     */
    public function getMutators($type)
    {
        return isset($this->config['mutators'][$type]) ? $this->config['mutators'][$type] : [];
    }

    /**
     * Get mutator alias if presented. Aliases used to simplify schema definition.
     *
     * @param string $alias
     * @return string|array
     */
    public function mutatorAlias($alias)
    {
        if (!is_string($alias) || !isset($this->config['mutatorAliases'][$alias])) {
            return $alias;
        }

        return $this->config['mutatorAliases'][$alias];
    }

    /**
     * Locate every available Document class.
     *
     * @param TokenizerInterface $tokenizer
     */
    protected function locateDocuments(TokenizerInterface $tokenizer)
    {
        foreach ($tokenizer->getClasses(Document::class) as $class => $definition) {
            if ($class == Document::class) {
                continue;
            }

            $this->documents[$class] = new DocumentSchema($this, $class);
        }
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
                //Already described by parent class
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
            ODM::DEFINITION_OPTIONS => $definition['options']
        ];
    }

    /**
     * Pack (normalize) document aggregations.
     *
     * @param array $aggregations
     * @return array
     */
    private function packAggregations(array $aggregations)
    {
        $result = [];
        foreach ($aggregations as $name => $aggregation) {
            $result[$name] = [
                ODM::AGR_TYPE       => $aggregation['type'],
                ODM::AGR_COLLECTION => $aggregation['collection'],
                ODM::AGR_DB         => $aggregation['database'],
                ODM::AGR_QUERY      => $aggregation['query']
            ];
        }

        return $result;
    }
}