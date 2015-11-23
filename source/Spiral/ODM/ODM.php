<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\ODM;

use Spiral\Core\Component;
use Spiral\Core\ConstructorInterface;
use Spiral\Core\Container\InjectorInterface;
use Spiral\Core\Container\SingletonInterface;
use Spiral\Core\HippocampusInterface;
use Spiral\Debug\Traits\BenchmarkTrait;
use Spiral\Models\DataEntity;
use Spiral\Models\SchematicEntity;
use Spiral\ODM\Configs\ODMConfig;
use Spiral\ODM\Entities\DocumentSelector;
use Spiral\ODM\Entities\DocumentSource;
use Spiral\ODM\Entities\MongoDatabase;
use Spiral\ODM\Entities\SchemaBuilder;
use Spiral\ODM\Exceptions\DefinitionException;
use Spiral\ODM\Exceptions\ODMException;
use Spiral\Tokenizer\ClassLocatorInterface;

/**
 * ODM component used to manage state of cached Document's schema, document creation and schema
 * analysis.
 */
class ODM extends Component implements SingletonInterface, InjectorInterface
{
    /**
     * Has it's own configuration, in addition MongoDatabase creation can take some time.
     */
    use BenchmarkTrait;

    /**
     * Declares to IoC that component instance should be treated as singleton.
     */
    const SINGLETON = self::class;

    /**
     * Memory section to store ODM schema.
     */
    const MEMORY = 'odmSchema';

    /**
     * Class definition options.
     */
    const DEFINITION         = 0;
    const DEFINITION_OPTIONS = 1;

    /**
     * Normalized document constants.
     */
    const D_DEFINITION   = self::DEFINITION;
    const D_COLLECTION   = 1;
    const D_DB           = 2;
    const D_SOURCE       = 3;
    const D_HIDDEN       = SchematicEntity::SH_HIDDEN;
    const D_SECURED      = SchematicEntity::SH_SECURED;
    const D_FILLABLE     = SchematicEntity::SH_FILLABLE;
    const D_MUTATORS     = SchematicEntity::SH_MUTATORS;
    const D_VALIDATES    = SchematicEntity::SH_VALIDATES;
    const D_DEFAULTS     = 9;
    const D_AGGREGATIONS = 10;
    const D_COMPOSITIONS = 11;

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
     * @var ODMConfig
     */
    protected $config = null;

    /**
     * Cached documents and collections schema.
     *
     * @var array|null
     */
    protected $schema = null;

    /**
     * Mongo databases instances.
     *
     * @var MongoDatabase[]
     */
    protected $databases = [];

    /**
     * @invisible
     * @var HippocampusInterface
     */
    protected $memory = null;

    /**
     * @invisible
     * @var ConstructorInterface
     */
    protected $constructor = null;

    /**
     * @param ODMConfig            $config
     * @param HippocampusInterface $memory
     * @param ConstructorInterface $constructor
     */
    public function __construct(
        ODMConfig $config,
        HippocampusInterface $memory,
        ConstructorInterface $constructor
    ) {
        $this->config = $config;
        $this->memory = $memory;

        //Loading schema from memory
        $this->schema = (array)$memory->loadData(static::MEMORY);
        $this->constructor = $constructor;
    }

    /**
     * Create specified or select default instance of MongoDatabase.
     *
     * @param string $database Database name (internal).
     * @return MongoDatabase
     * @throws ODMException
     */
    public function database($database = null)
    {
        if (empty($database)) {
            $database = $this->config->defaultDatabase();
        }

        //Spiral support ability to link multiple virtual databases together using aliases
        $database = $this->config->resolveAlias($database);

        if (isset($this->databases[$database])) {
            return $this->databases[$database];
        }

        if (!$this->config->hasDatabase($database)) {
            throw new ODMException(
                "Unable to initiate mongo database, no presets for '{$database}' found."
            );
        }

        $benchmark = $this->benchmark('database', $database);
        try {
            $this->databases[$database] = $this->constructor->construct(MongoDatabase::class, [
                'name'   => $database,
                'config' => $this->config->databaseConfig($database),
                'odm'    => $this
            ]);
        } finally {
            $this->benchmark($benchmark);
        }

        return $this->databases[$database];
    }

    /**
     * {@inheritdoc}
     */
    public function createInjection(\ReflectionClass $class, $context = null)
    {
        return $this->database($context);
    }

    /**
     * Create instance of document by given class name and set of fields, ODM component must
     * automatically find appropriate class to be used as ODM support model inheritance.
     *
     * @param string                $class
     * @param array                 $fields
     * @param CompositableInterface $parent
     * @return Document
     * @throws DefinitionException
     */
    public function document($class, array $fields, CompositableInterface $parent = null)
    {
        $class = $this->defineClass($class, $fields, $schema);

        return new $class($fields, $parent, $this, $schema);
    }

    /**
     * Get instance of ODM source associated with given model class.
     *
     * @param string $class
     * @return DocumentSource
     */
    public function source($class)
    {
        $schema = $this->schema($class);
        if (empty($source = $schema[self::D_SOURCE])) {
            $source = DocumentSource::class;
        }

        return new $source($class, $this);
    }

    /**
     * Mongo collection associated with given model class.
     *
     * @param string $class
     * @return \MongoCollection
     */
    public function mongoCollection($class)
    {
        $schema = $this->schema($class);

        return $this->database($schema[ODM::D_DB])->selectCollection($schema[ODM::D_COLLECTION]);
    }

    /**
     * Get instance of ODM Selector associated with given class.
     *
     * @param       $class
     * @param array $query
     * @return DocumentSelector
     */
    public function selector($class, array $query = [])
    {
        $schema = $this->schema($class);

        return new DocumentSelector($this, $schema[ODM::D_DB], $schema[ODM::D_COLLECTION], $query);
    }

    /**
     * Define document class using it's fieldset and definition.
     *
     * @see Document::DEFINITION
     * @param string $class
     * @param array  $fields
     * @param array  $schema Found class schema, reference.
     * @return string
     * @throws DefinitionException
     */
    public function defineClass($class, $fields, &$schema = [])
    {
        $schema = $this->schema($class);

        $definition = $schema[self::D_DEFINITION];
        if (is_string($definition)) {
            //Document has no variations
            return $definition;
        }

        if (!is_array($fields)) {
            //Unable to resolve
            return $class;
        }

        $defined = $class;
        if ($definition[self::DEFINITION] == DocumentEntity::DEFINITION_LOGICAL) {

            //Resolve using logic function
            $defined = call_user_func($definition[self::DEFINITION_OPTIONS], $fields, $this);

            if (empty($defined)) {
                throw new DefinitionException(
                    "Unable to resolve (logical definition) valid class for document '{$class}'."
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

        //Child may change definition method or declare it's own children
        return $defined == $class ? $class : $this->defineClass($defined, $fields, $schema);
    }

    /**
     * Get cached schema data by it's item name (document name, collection name).
     *
     * @param string $item
     * @return array|string
     * @throws ODMException
     */
    public function schema($item)
    {
        if (!isset($this->schema[$item])) {
            $this->updateSchema();
        }

        if (!isset($this->schema[$item])) {
            throw new ODMException("Undefined ODM schema item '{$item}'.");
        }

        return $this->schema[$item];
    }

    /**
     * Get primary document class to be associated with collection. Attention, collection may return
     * parent document instance even if query was made using children implementation.
     *
     * @param string $database
     * @param string $collection
     * @return string
     */
    public function primaryDocument($database, $collection)
    {
        return $this->schema($database . '/' . $collection);
    }

    /**
     * Update ODM documents schema and return instance of SchemaBuilder.
     *
     * @param SchemaBuilder         $builder User specified schema builder.
     * @param ClassLocatorInterface $locator
     * @return SchemaBuilder
     */
    public function updateSchema(
        SchemaBuilder $builder = null,
        ClassLocatorInterface $locator = null
    ) {
        if (empty($builder)) {
            $builder = $this->schemaBuilder($locator);
        }

        //We will create all required indexes now
        $builder->createIndexes();

        //Getting cached/normalized schema
        $this->schema = $builder->normalizeSchema();

        //Saving
        $this->memory->saveData(static::MEMORY, $this->schema);

        //Let's reinitialize models
        DataEntity::resetInitiated();

        return $builder;
    }

    /**
     * Get instance of ODM SchemaBuilder.
     *
     * @param ClassLocatorInterface $locator
     * @return SchemaBuilder
     */
    public function schemaBuilder(ClassLocatorInterface $locator = null)
    {
        return $this->constructor->construct(SchemaBuilder::class, [
            'odm'     => $this,
            'config'  => $this->config['schemas'],
            'locator' => $locator
        ]);
    }

    /**
     * Create valid MongoId object based on string or id provided from client side.
     *
     * @param mixed $mongoID String or MongoId object.
     * @return \MongoId|null
     */
    public static function mongoID($mongoID)
    {
        if (empty($mongoID)) {
            return null;
        }

        if (!is_object($mongoID)) {
            //Old versions of mongo api does not throws exception on invalid mongo id (1.2.1)
            if (!is_string($mongoID) || !preg_match('/[0-9a-f]{24}/', $mongoID)) {
                return null;
            }

            try {
                $mongoID = new \MongoId($mongoID);
            } catch (\Exception $exception) {
                return null;
            }
        }

        return $mongoID;
    }
}