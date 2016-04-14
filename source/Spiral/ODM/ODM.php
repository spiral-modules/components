<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\ODM;

use Spiral\Core\Container\SingletonInterface;
use Spiral\Core\FactoryInterface;
use Spiral\Core\HippocampusInterface;
use Spiral\Debug\Traits\BenchmarkTrait;
use Spiral\Models\SchematicEntity;
use Spiral\ODM\Configs\ODMConfig;
use Spiral\ODM\Exceptions\DefinitionException;

/**
 * ODM Component joins functionality for MongoDatabase management and document classes creation.
 */
class ODM extends MongoManager implements SingletonInterface, ODMInterface
{
    use BenchmarkTrait;

    /**
     * Memory section to store ODM schema.
     */
    const MEMORY = 'odm.schema';




    /**
     * Normalized aggregation constants.
     */
    const AGR_TYPE  = 1;
    const ARG_CLASS = 2;
    const AGR_QUERY = 3;

    /**
     * Normalized composition constants.
     */
    const CMP_TYPE  = 0;
    const CMP_CLASS = 1;
    const CMP_ONE   = 0x111;
    const CMP_MANY  = 0x222;
    const CMP_HASH  = 0x333;

    /**
     * Cached documents and collections schema.
     *
     * @var array|null
     */
    protected $schema = null;

    /**
     * @invisible
     *
     * @var HippocampusInterface
     */
    protected $memory = null;

    /**
     * @param ODMConfig            $config
     * @param HippocampusInterface $memory
     * @param FactoryInterface     $factory
     */
    public function __construct(
        ODMConfig $config,
        HippocampusInterface $memory,
        FactoryInterface $factory
    ) {
        parent::__construct($config, $factory);

        //Loading schema from memory
        $this->memory = $memory;
        $this->schema = (array)$memory->loadData(static::MEMORY);
    }

    /**
     * {@inheritdoc}
     */
    public function document($class, $fields, CompositableInterface $parent = null)
    {
        $class = $this->defineClass($class, $fields, $schema);

        return new $class($fields, $parent, $this, $schema);
    }

    public function selector($class, array $query = [])
    {

    }

    public function source($class)
    {

    }

    public function saver($class)
    {

    }

    /**
     * Define document class using it's fieldset and definition.
     *
     * @see DocumentEntity::DEFINITION
     *
     * @param string $class
     * @param array  $fields
     * @param array  $schema Found class schema, reference.
     *
     * @return string
     *
     * @throws DefinitionException
     */
    protected function defineClass($class, $fields, &$schema = [])
    {

    }
}