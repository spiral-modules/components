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
use Spiral\Models\MutableObject;
use Spiral\ODM\Configs\ODMConfig;
use Spiral\ODM\Entities\DocumentMapper;
use Spiral\ODM\Entities\DocumentSelector;
use Spiral\ODM\Entities\DocumentSource;
use Spiral\ODM\Entities\SchemaBuilder;
use Spiral\ODM\Exceptions\DefinitionException;
use Spiral\ODM\Exceptions\ODMException;
use Spiral\Tokenizer\ClassLocatorInterface;

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
     * Document mappers.
     *
     * @var DocumentMapper[]
     */
    private $mappers = [];

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
    public function schema($item, $property = null)
    {
        if (!isset($this->schema[$item])) {
            $this->updateSchema();
        }

        if (!isset($this->schema[$item])) {
            throw new ODMException("Undefined ODM schema item '{$item}'");
        }

        if (!empty($property)) {
            if (!array_key_exists($property, $this->schema[$item])) {
                throw new ODMException("Undefined schema property '{$property}' of '{$item}'");
            }

            return $this->schema[$item][$property];
        }

        return $this->schema[$item];
    }

    /**
     * {@inheritdoc}
     */
    public function document($class, $fields = [], CompositableInterface $parent = null)
    {
        $class = $this->defineClass($class, $fields, $schema);

        return new $class($fields, $parent, $this, $schema);
    }

    /**
     * {@inheritdoc}
     */
    public function selector($class, array $query = [])
    {
        return new DocumentSelector($this, $class, $query);
    }

    /**
     * {@inheritdoc}
     */
    public function source($class)
    {
        $source = $this->schema($class, self::D_SOURCE);
        if (empty($source)) {
            //Default source class
            $source = DocumentSource::class;
        }

        return new $source($class, $this);
    }

    /**
     * Set custom instance of mapper for a given ODM model.
     *
     * @param string         $class
     * @param DocumentMapper $mapper
     */
    public function setMapper($class, DocumentMapper $mapper)
    {
        $this->mappers[$class] = $mapper;
    }

    /**
     * {@inheritdoc}
     */
    public function mapper($class)
    {
        if (isset($this->mappers[$class])) {
            return $this->mappers[$class];
        }

        $mapper = $this->factory->make(DocumentMapper::class, [
            'odm'   => $this,
            'class' => $class
        ]);

        return $this->mappers[$class] = $mapper;
    }

    /**
     * Update ODM documents schema and return instance of SchemaBuilder.
     *
     * @param SchemaBuilder $builder User specified schema builder.
     * @param bool          $createIndexes
     *
     * @return SchemaBuilder
     */
    public function updateSchema(SchemaBuilder $builder = null, $createIndexes = false)
    {
        if (empty($builder)) {
            $builder = $this->schemaBuilder();
        }

        //We will create all required indexes now
        if ($createIndexes) {
            $builder->createIndexes();
        }

        //Getting cached/normalized schema
        $this->schema = $builder->normalizeSchema();

        //Saving
        $this->memory->saveData(static::MEMORY, $this->schema);

        //Let's reinitialize models (todo, make sure not harm can be made)
        MutableObject::resetInitiated();

        return $builder;
    }

    /**
     * Get instance of ODM SchemaBuilder.
     *
     * @param ClassLocatorInterface $locator
     *
     * @return SchemaBuilder
     */
    public function schemaBuilder(ClassLocatorInterface $locator = null)
    {
        return $this->factory->make(SchemaBuilder::class, [
            'odm'     => $this,
            'config'  => $this->config,
            'locator' => $locator
        ]);
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
        $schema = $this->schema($class);

        $definition = $schema[self::D_DEFINITION];
        if (is_string($definition)) {
            //Document has no variations
            return $definition;
        }

        if (!is_array($fields)) {
            //Unable to resolve for non array set, using same class as given
            return $class;
        }

        $defined = $class;
        if ($definition[self::DEFINITION] == DocumentEntity::DEFINITION_LOGICAL) {

            //Resolve using logic function
            $defined = call_user_func($definition[self::DEFINITION_OPTIONS], $fields, $this);

            if (empty($defined)) {
                throw new DefinitionException(
                    "Unable to resolve (logical definition) valid class for document '{$class}'"
                );
            }
        } elseif ($definition[self::DEFINITION] == DocumentEntity::DEFINITION_FIELDS) {
            foreach ($definition[self::DEFINITION_OPTIONS] as $field => $child) {
                if (array_key_exists($field, $fields)) {
                    //Apparently this is child
                    $defined = $child;
                    break;
                }
            }
        }

        if ($defined != $class) {
            //Child may change definition method or declare it's own children
            return $this->defineClass($defined, $fields, $schema);
        }

        return $class;
    }
}