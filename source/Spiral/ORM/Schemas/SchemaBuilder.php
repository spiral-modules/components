<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Schemas;

use Spiral\Database\DatabaseManager;
use Spiral\ORM\Exceptions\SchemaException;
use Spiral\ORM\ORMInterface;

class SchemaBuilder
{
    /**
     * @var DatabaseManager
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
     * @param DatabaseManager $manager
     */
    public function __construct(DatabaseManager $manager)
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
            throw new SchemaException("Unable to add source to '{$class}', class is unknown to ORM");
        }

        $this->sources[$class] = $source;

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
            $result[$class][] = [
                ORMInterface::R_INSTANTIATOR => $schema->getInstantiator(),
                ORMInterface::R_SCHEMA       => $schema->packSchema($this),
                ORMInterface::R_SOURCE_CLASS => $this->getSource($class),
                ORMInterface::R_DATABASE     => $schema->getDatabase(),
                ORMInterface::R_TABLE        => $schema->getTable()
            ];
        }

        return $result;
    }
}