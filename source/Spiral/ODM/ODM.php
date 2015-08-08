<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
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
use Spiral\ODM\Entities\Collection;
use Spiral\ODM\Entities\MongoDatabase;
use Spiral\ODM\Entities\SchemaBuilder;
use Spiral\ODM\Exceptions\DefinitionException;
use Spiral\ODM\Exceptions\ODMException;
use Spiral\ORM\Exceptions\ORMException;

/**
 * ODM class used to manage state of cached Document's schema, document creation and schema analysis.
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
    const D_HIDDEN       = 4;
    const D_SECURED      = 5;
    const D_FILLABLE     = 6;
    const D_MUTATORS     = 7;
    const D_VALIDATES    = 8;
    const D_AGGREGATIONS = 9;
    const D_COMPOSITIONS = 10;

    /**
     * Normalized aggregation constants.
     */
    const AGR_TYPE  = 0;
    const AGR_QUERY = 1;

    /**
     * Matched to D_COLLECTION and D_DB to use in Document::odmCollection() method. But this is still aggregation
     * constants.
     */
    const AGR_COLLECTION = 0;
    const AGR_DB         = 1;

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
        $this->schema = $memory->loadData('odmSchema');

        $this->memory = $memory;
        $this->container = $container;
    }

    /**
     * Create specified or select default instance of MongoDatabase.
     *
     * @param string $database Database name (internal).
     * @param array  $config   Connection options, only required for databases not listed in ODM config.
     * @return MongoDatabase
     * @throws ODMException
     */
    public function db($database = null, array $config = [])
    {
        $database = !empty($database) ? $database : $this->config['default'];
        if (isset($this->config['aliases'][$database])) {
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

        $this->benchmark('database', $database);
        $this->databases[$database] = $this->container->get(MongoDatabase::class, [
            'name'   => $database,
            'config' => $config,
            'odm'    => $this
        ]);
        $this->benchmark('database', $database);

        return $this->databases[$database];
    }

    /**
     * {@inheritdoc}
     */
    public function createInjection(\ReflectionClass $class, \ReflectionParameter $parameter)
    {
        return $this->db($parameter->getName());
    }

    /**
     * Create instance of document by given class name and set of fields, ODM component must automatically find appropriate
     * class to be used as ODM support model inheritance.
     *
     * @param string $class
     * @param array  $fields
     * @return Document
     * @throws DefinitionException
     */
    public function document($class, array $fields)
    {
        $class = $this->defineClass($class, $fields, $schema);

        return new $class($fields, null, $this, $schema);
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
     * Get cached schema data by it's item name (document name, collection name).
     *
     * @param string $item
     * @return mixed
     * @throws ODMException
     */
    public function getSchema($item)
    {
        if (!isset($this->schema[$item])) {
            $this->updateSchema();
        }

        if (!isset($this->schema[$item])) {
            throw new ORMException("Undefined ODM schema item '{$item}'.");
        }

        return $this->schema[$item];
    }

    /**
     * Get primary document class to be associated with collection. Attention, collection may return parent document
     * instance even if query was made using children implementation.
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
     * Get instance of ODM SchemaBuilder.
     *
     * @return SchemaBuilder
     */
    public function schemaBuilder()
    {
        return $this->container->get(SchemaBuilder::class, [
            'odm'    => $this,
            'config' => $this->config['schemas']
        ]);
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
        $this->memory->saveData('odmSchema', $this->schema = $builder->normalizeSchema());

        //Let's reinitialize models
        DataEntity::resetInitiated();

        return $builder;
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
    protected function defineClass($class, $fields, &$schema = [])
    {
        //        //get definition from there
        //
        //        if (is_string($definition)) {
        //            return $definition;
        //        }
        //
        //        if ($definition[self::DEFINITION] == Document::DEFINITION_LOGICAL) {
        //            //Function based
        //            $definition = call_user_func($definition[self::DEFINITION_OPTIONS], $fields);
        //        } else {
        //            //Property based
        //            foreach ($definition[self::DEFINITION_OPTIONS] as $class => $field) {
        //                $definition = $class;
        //                if (array_key_exists($field, $fields)) {
        //                    break;
        //                }
        //            }
        //        }
        //
        //        return $definition;
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