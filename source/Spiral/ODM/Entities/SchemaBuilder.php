<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ODM\Entities;

use Spiral\Core\Traits\ConfigurableTrait;
use Spiral\ODM\Document;
use Spiral\ODM\Entities\Schemas\CollectionSchema;
use Spiral\ODM\Entities\Schemas\DocumentSchema;
use Spiral\ODM\Exceptions\SchemaException;
use Spiral\ODM\ODM;
use Spiral\Tokenizer\TokenizerInterface;

/**
 * Schema builder responsible for static analysis of existed Documents, their schemas, validations, requested indexes
 * and etc.
 */
class SchemaBuilder
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
     * Check if Document class known to schema bulder.
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

    protected function describeCollections()
    {
        foreach ($this->getDocuments() as $schema) {
            if (empty($collection = $schema->getCollection())) {
                //Skip embedded models
                continue;
            }

            //Getting fully specified collection name (with specified db)
            $collection = $schema->getDatabase() . '/' . $collection;

            if (!isset($this->collections[$collection])) {
                $primaryDocument = $this->documentSchema($schema->getParent());

                if ($schema->getCollection() == $primaryDocument->getCollection()) {
                    //Child document use same collection as parent?
                    $this->collections[$collection] = new CollectionSchema(
                        $this,
                        $primaryDocument->getCollection(),
                        $primaryDocument->getDatabase(),
                        $primaryDocument->classDefinition(),
                        $primaryDocument->getClass()
                    );
                } else {

                    $this->collections[$collection] = new CollectionSchema(
                        $this,
                        $schema->getCollection(),
                        $schema->getDatabase(),
                        $schema->classDefinition(),
                        $schema->getClass()

                    );
                }
            }
        }
    }
}