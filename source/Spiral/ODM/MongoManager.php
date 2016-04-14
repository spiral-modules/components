<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\ODM;

use Spiral\Core\Component;
use Spiral\Core\Container\InjectorInterface;
use Spiral\Core\FactoryInterface;
use Spiral\Debug\Traits\BenchmarkTrait;
use Spiral\ODM\Configs\ODMConfig;
use Spiral\ODM\Entities\MongoDatabase;
use Spiral\ODM\Exceptions\ODMException;

/**
 * Manages mongo databases.
 */
class MongoManager extends Component implements InjectorInterface
{
    use BenchmarkTrait;

    /**
     * @var MongoDatabase[]
     */
    private $databases = [];

    /**
     * @var ODMConfig
     */
    protected $config = null;

    /**
     * @invisible
     *
     * @var FactoryInterface
     */
    protected $factory = null;

    /**
     * @param ODMConfig        $config
     * @param FactoryInterface $factory
     */
    public function __construct(ODMConfig $config, FactoryInterface $factory)
    {
        $this->config = $config;
        $this->factory = $factory;
    }

    /**
     * Add new database under given name.
     *
     * @param MongoDatabase $database
     * @return $this
     *
     * @throws ODMException
     */
    public function addDatabase(MongoDatabase $database)
    {
        if (isset($this->databases[$database->getName()])) {
            throw new ODMException("Database '{$database->getName()}' already exists");
        }

        $this->databases[$database->getName()] = $database;

        return $this;
    }

    /**
     * Register new mongo database using given name and connectio options (compatible with MongoDB
     * class).
     *
     * @param string $name
     * @param string $server
     * @param string $database
     * @param array  $options
     *
     * @return MongoDatabase
     */
    public function registerDatabase($name, $server, $database, array $options = [])
    {
        $benchmark = $this->benchmark('database', $name);
        try {
            $instance = $this->factory->make(MongoDatabase::class, [
                'name'   => $database,
                'config' => compact('server', 'database', 'options')
            ]);
        } finally {
            $this->benchmark($benchmark);
        }

        $this->addDatabase($instance);

        return $instance;
    }

    /**
     * Create specified or select default instance of MongoDatabase.
     *
     * @param string $database Database name (internal).
     *
     * @return MongoDatabase
     *
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
                "Unable to initiate MongoDatabase, no presets for '{$database}' found"
            );
        }

        $benchmark = $this->benchmark('database', $database);
        try {
            $this->databases[$database] = $this->factory->make(MongoDatabase::class, [
                'name'   => $database,
                'config' => $this->config->databaseConfig($database),
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
     * Create valid MongoId object based on string or id provided from client side.
     *
     * @param mixed $mongoID String or MongoId object.
     *
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
            } catch (\Exception $e) {
                return null;
            }
        }

        return $mongoID;
    }
}