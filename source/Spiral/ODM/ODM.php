<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\ODM;

use Spiral\Core\ConfiguratorInterface;
use Spiral\Core\Container\InjectorInterface;
use Spiral\Core\ContainerInterface;
use Spiral\Core\HippocampusInterface;
use Spiral\Core\Singleton;
use Spiral\Core\Traits\ConfigurableTrait;
use Spiral\Debug\Traits\BenchmarkTrait;
use Spiral\Models\DataEntity;
use Spiral\Models\SchematicEntity;
use Spiral\ODM\Entities\Collection;
use Spiral\ODM\Entities\MongoDatabase;
use Spiral\ODM\Entities\SchemaBuilder;
use Spiral\ODM\Entities\Source;
use Spiral\ODM\Exceptions\DefinitionException;
use Spiral\ODM\Exceptions\ODMException;
use Spiral\Tokenizer\TokenizerInterface;

/**
 * ODM component used to manage state of cached Document's schema, document creation and schema
 * analysis.
 */
class ODM extends Singleton implements InjectorInterface
{
    /**
     * Has it's own configuration, in addition MongoDatabase creation can take some time.
     */
    use ConfigurableTrait, BenchmarkTrait;

    /**
     * Declares to IoC that component instance should be treated as singleton.
     */
    const SINGLETON = self::class;

    /**
     * Configuration section.
     */
    const CONFIG = 'odm';

    /**
     * Memory section to store ODM schema.
     */
    const SCHEMA_SECTION = 'odmSchema';

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
    const D_DEFAULTS     = 3;
    const D_HIDDEN       = SchematicEntity::SH_HIDDEN;
    const D_SECURED      = SchematicEntity::SH_SECURED;
    const D_FILLABLE     = SchematicEntity::SH_FILLABLE;
    const D_MUTATORS     = SchematicEntity::SH_MUTATORS;
    const D_VALIDATES    = SchematicEntity::SH_VALIDATES;
    const D_AGGREGATIONS = 9;
    const D_COMPOSITIONS = 10;

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
     * @var ContainerInterface
     */
    protected $container = null;

    /**
     * @param ConfiguratorInterface $configurator
     * @param HippocampusInterface  $memory
     * @param ContainerInterface    $container
     */
    public function __construct(
        ConfiguratorInterface $configurator,
        HippocampusInterface $memory,
        ContainerInterface $container
    ) {
        $this->config = $configurator->getConfig(static::CONFIG);
        $this->schema = (array)$memory->loadData(static::SCHEMA_SECTION);

        $this->memory = $memory;
        $this->container = $container;
    }

    /**
     * Create specified or select default instance of MongoDatabase.
     *
     * @param string $database Database name (internal).
     * @param array  $config   Connection options, only required for databases not listed in ODM
     *                         config.
     * @return MongoDatabase
     * @throws ODMException
     */
    public function db($database = null, array $config = [])
    {
        $database = !empty($database) ? $database : $this->config['default'];
        while (isset($this->config['aliases'][$database])) {
            $database = $this->config['aliases'][$database];
        }

        if (isset($this->databases[$database])) {
            return $this->databases[$database];
        }

        if (empty($config)) {
            if (!isset($this->config['databases'][$database])) {
                throw new ODMException(
                    "Unable to initiate mongo database, no presets for '{$database}' found."
                );
            }

            $config = $this->config['databases'][$database];
        }

        $benchmark = $this->benchmark('database', $database);
        $this->databases[$database] = $this->container->construct(MongoDatabase::class, [
            'name'   => $database,
            'config' => $config,
            'odm'    => $this
        ]);
        $this->benchmark($benchmark);

        return $this->databases[$database];
    }

    /**
     * {@inheritdoc}
     */
    public function createInjection(\ReflectionClass $class, $context)
    {
        return $this->db($context);
    }

    /**
     * Get cached schema data by it's item name (document name, collection name).
     *
     * @param string $item
     * @return array|string
     * @throws ODMException
     */
    public function getSchema($item)
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
     * @return Source
     */
    public function documentSource($class)
    {

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
        $schema = $this->getSchema($class);

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
     * Instance of ODM Collection associated with specified document class.
     *
     * @param string $class
     * @return Collection
     * @throws ODMException
     */
    public function odmCollection($class)
    {
        $schema = $this->getSchema($class);

        if (empty($schema[self::D_DB])) {
            throw new ODMException("Document '{$class}' does not have any associated collection.");
        }

        return new Collection($this, $schema[self::D_DB], $schema[self::D_COLLECTION], []);
    }

    /**
     * Get primary document class to be associated with collection. Attention, collection may return
     * parent document instance even if query was made using children implementation.
     *
     * @param string $database
     * @param string $collection
     * @return string
     */
    public function collectionClass($database, $collection)
    {
        return $this->getSchema($database . '/' . $collection);
    }

    /**
     * Update ODM documents schema and return instance of SchemaBuilder.
     *
     * @param SchemaBuilder $builder User specified schema builder.
     * @return SchemaBuilder
     */
    public function updateSchema(SchemaBuilder $builder = null)
    {
        $builder = !empty($builder) ? $builder : $this->schemaBuilder();

        //We will create all required indexes now
        $builder->createIndexes();

        //Saving
        $this->memory->saveData(
            static::SCHEMA_SECTION,
            $this->schema = $builder->normalizeSchema()
        );

        //Let's reinitialize models
        DataEntity::resetInitiated();

        return $builder;
    }

    /**
     * Get instance of ODM SchemaBuilder.
     *
     * @param TokenizerInterface $tokenizer
     * @return SchemaBuilder
     */
    public function schemaBuilder(TokenizerInterface $tokenizer = null)
    {
        return $this->container->construct(SchemaBuilder::class, [
            'odm'       => $this,
            'config'    => $this->config['schemas'],
            'tokenizer' => $tokenizer
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