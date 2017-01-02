<?php
/**
 * Spiral Framework, Core Components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ODM;

use MongoDB\Driver\Manager;
use Spiral\Core\Container;
use Spiral\Core\Container\InjectorInterface;
use Spiral\Core\Container\SingletonInterface;
use Spiral\Core\FactoryInterface;
use Spiral\ODM\Configs\MongoConfig;
use Spiral\ODM\Entities\MongoDatabase;
use Spiral\ODM\Exceptions\ODMException;

class MongoManager implements InjectorInterface, SingletonInterface
{
    /**
     * @var MongoDatabase[]
     */
    private $databases = [];

    /**
     * @var MongoConfig
     */
    protected $config;

    /**
     * @var FactoryInterface
     */
    protected $factory;

    /**
     * @param MongoConfig      $config
     * @param FactoryInterface $factory
     */
    public function __construct(MongoConfig $config, FactoryInterface $factory = null)
    {
        $this->config = $config;
        $this->factory = $factory ?? new Container();
    }

    /**
     * Add new database in a database pull, database name will be automatically selected from given
     * instance.
     *
     * @param string        $name Internal database name.
     * @param MongoDatabase $database
     *
     * @return self|$this
     *
     * @throws ODMException
     */
    public function addDatabase(string $name, MongoDatabase $database): MongoManager
    {
        if (isset($this->databases[$name])) {
            throw new ODMException("Database '{$name}' already exists");
        }

        $this->databases[$name] = $database;

        return $this;
    }

    /**
     * Register new mongo database using given name and connection options (compatible with MongoDB
     * class).
     *
     * @param string $name     Internal database name (for injections and etc).
     * @param string $server   Server uri.
     * @param string $database Database name.
     * @param array  $driverOptions
     * @param array  $options  Database options.
     *
     * @return MongoDatabase
     */
    public function createDatabase(
        string $name,
        string $server,
        string $database,
        array $driverOptions = [],
        array $options = []
    ): MongoDatabase {
        //Database will be automatically connected here.
        $instance = $this->factory->make(MongoDatabase::class, [
            'databaseName' => $database,
            'manager'      => new Manager($server, $options, $driverOptions),
            'options'      => $options
        ]);

        $this->addDatabase($name, $instance);

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
    public function database(string $database = null): MongoDatabase
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

        $options = $this->config->databaseOptions($database);

        //Initiating database instance
        return $this->createDatabase(
            $database,
            $options['server'],
            $options['database'],
            $options['driverOptions'] ?? [],
            $options['options'] ?? []
        );
    }

    /**
     * Get every know database.
     *
     * @return MongoDatabase[]
     */
    public function getDatabases(): array
    {
        $result = [];

        //Include manually added databases
        foreach ($this->config->databaseNames() as $name) {
            $result[] = $this->database($name);
        }

        return $result;
    }

    /**
     * Automatic injection of MongoDatabase.
     *
     * {@inheritdoc}
     *
     * @return MongoDatabase
     */
    public function createInjection(\ReflectionClass $class, string $context = null)
    {
        return $this->database($context);
    }
}