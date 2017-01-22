<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ODM\Schemas;

use MongoDB\Driver\Exception\RuntimeException as DriverException;
use MongoDB\Exception\UnsupportedException;
use Spiral\ODM\Exceptions\SchemaException;
use Spiral\ODM\MongoManager;
use Spiral\ODM\ODMInterface;

class SchemaBuilder
{
    /**
     * @var MongoManager
     */
    private $manager;

    /**
     * @var SchemaInterface[]
     */
    private $schemas = [];

    /**
     * Class names of sources associated with specific class.
     *
     * @var array
     */
    private $sources = [];

    /**
     * @param MongoManager $manager
     */
    public function __construct(MongoManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Add new model schema into pool.
     *
     * @param SchemaInterface $schema
     *
     * @return self|$this
     */
    public function addSchema(SchemaInterface $schema): SchemaBuilder
    {
        $this->schemas[$schema->getClass()] = $schema;

        return $this;
    }

    /**
     * @param string $class
     *
     * @return bool
     */
    public function hasSchema(string $class): bool
    {
        return isset($this->schemas[$class]);
    }

    /**
     * @param string $class
     *
     * @return SchemaInterface
     *
     * @throws SchemaException
     */
    public function getSchema(string $class): SchemaInterface
    {
        if (!$this->hasSchema($class)) {
            throw new SchemaException("Unable to find schema for class '{$class}'");
        }

        return $this->schemas[$class];
    }

    /**
     * All available document schemas.
     *
     * @return SchemaInterface[]
     */
    public function getSchemas(): array
    {
        return $this->schemas;
    }

    /**
     * Associate source class with entity class. Source will be automatically associated with given
     * class and all classes from the same collection which extends it.
     *
     * @param string $class
     * @param string $source
     *
     * @return SchemaBuilder
     *
     * @throws SchemaException
     */
    public function addSource(string $class, string $source): SchemaBuilder
    {
        if (!$this->hasSchema($class)) {
            throw new SchemaException("Unable to add source to '{$class}', class is unknown to ODM");
        }

        //See usage below
        $scope = [
            $this->getSchema($class)->getDatabase(),
            $this->getSchema($class)->getCollection()
        ];

        //Ensuring same source for all children classes from same collection
        foreach ($this->schemas as $schema) {
            if (isset($this->sources[$schema->getClass()])) {
                //Already set
                continue;
            }

            if (is_a($schema->getClass(), $class, true)) {
                //Only for entities from the same collection
                if ([$schema->getDatabase(), $schema->getCollection()] == $scope) {
                    $this->sources[$schema->getClass()] = $source;
                }
            }
        }

        return $this;
    }

    /**
     * Check if given entity has associated source.
     *
     * @param string $class
     *
     * @return bool
     */
    public function hasSource(string $class): bool
    {
        return array_key_exists($class, $this->sources);
    }

    /**
     * Get source associated with specific class, if any.
     *
     * @param string $class
     *
     * @return string|null
     */
    public function getSource(string $class)
    {
        if (!$this->hasSource($class)) {
            return null;
        }

        return $this->sources[$class];
    }

    /**
     * Pack declared schemas in a normalized form.
     *
     * @return array
     */
    public function packSchema(): array
    {
        $result = [];
        foreach ($this->schemas as $class => $schema) {
            $item = [
                //Instantiator class
                ODMInterface::D_INSTANTIATOR  => $schema->getInstantiator(),

                //Primary collection class
                ODMInterface::D_PRIMARY_CLASS => $schema->resolvePrimary($this),

                //Instantiator and entity specific schema
                ODMInterface::D_SCHEMA        => $schema->packSchema($this),
            ];

            if (!$schema->isEmbedded()) {
                $item[ODMInterface::D_SOURCE_CLASS] = $this->getSource($class);
                $item[ODMInterface::D_DATABASE] = $schema->getDatabase();
                $item[ODMInterface::D_COLLECTION] = $schema->getCollection();
            }

            $result[$class] = $item;
        }

        return $result;
    }

    /**
     * Create all declared indexes.
     *
     * @throws UnsupportedException
     * @throws DriverException
     */
    public function createIndexes()
    {
        foreach ($this->schemas as $class => $schema) {
            if ($schema->isEmbedded()) {
                continue;
            }

            $collection = $this->manager->database(
                $schema->getDatabase()
            )->selectCollection(
                $schema->getCollection()
            );

            //Declaring needed indexes
            foreach ($schema->getIndexes() as $index) {
                $collection->createIndex($index->getIndex(), $index->getOptions());
            }
        }
    }
}