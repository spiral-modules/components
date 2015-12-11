<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Database;

use Spiral\Core\Component;
use Spiral\Core\FactoryInterface;
use Spiral\Core\Container\InjectorInterface;
use Spiral\Database\Configs\DatabasesConfig;
use Spiral\Database\Entities\Database;
use Spiral\Database\Entities\Driver;
use Spiral\Database\Exceptions\DatabaseException;

/**
 * DatabaseManager responsible for database creation, configuration storage and drivers factory.
 */
class DatabaseManager extends Component implements InjectorInterface, DatabasesInterface
{
    /**
     * Declares to Spiral IoC that component instance should be treated as singleton.
     */
    const SINGLETON = self::class;

    /**
     * By default spiral will force time conversion into single timezone before storing in
     * database, it will help us to ensure that we have no problems with switching timezones and
     * save a lot of time while development (potentially).
     * 
     * Current implementation of drivers leaves a room and possibility to define driver/connection
     * specific timezone.
     */
    const DEFAULT_TIMEZONE = 'UTC';

    /**
     * @var Database[]
     */
    private $databases = [];

    /**
     * @var Driver[]
     */
    private $drivers = [];

    /**
     * @var DatabasesConfig
     */
    protected $config = null;

    /**
     * @invisible
     * @var FactoryInterface
     */
    protected $factory = null;

    /**
     * @param DatabasesConfig  $config
     * @param FactoryInterface $factory
     */
    public function __construct(DatabasesConfig $config, FactoryInterface $factory)
    {
        $this->config = $config;
        $this->factory = $factory;
    }

    /**
     * {@inheritdoc}
     *
     * @return Database
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
            throw new DatabaseException(
                "Unable to create database, no presets for '{$database}' found."
            );
        }

        //No need to benchmark here, due connection will happen later
        $this->databases[$database] = $this->factory->make(Database::class, [
            'name'   => $database,
            'driver' => $this->driver($this->config->databaseConnection($database)),
            'prefix' => $this->config->databasePrefix($database)
        ]);

        return $this->databases[$database];
    }

    /**
     * Get driver by it's name. Every driver associated with configured connection, there is minor
     * de-sync in naming due legacy code.
     *
     * @param string $connection
     * @return Driver
     * @throws DatabaseException
     */
    public function driver($connection)
    {
        if (isset($this->drivers[$connection])) {
            return $this->drivers[$connection];
        }

        if (!$this->config->hasConnection($connection)) {
            throw new DatabaseException(
                "Unable to create Driver, no presets for '{$connection}' found."
            );
        }

        $this->drivers[$connection] = $this->factory->make(
            $this->config->connectionDriver($connection),
            [
                'name'   => $connection,
                'config' => $this->config->connectionConfig($connection)
            ]
        );

        return $this->drivers[$connection];
    }

    /**
     * Get instance of every available database.
     *
     * @return Database[]
     * @throws DatabaseException
     */
    public function getDatabases()
    {
        $result = [];
        foreach ($this->config->databaseNames() as $name) {
            $result[] = $this->database($name);
        }

        return $result;
    }

    /**
     * Get instance of every available driver/connection.
     *
     * @return Driver[]
     * @throws DatabaseException
     */
    public function getDrivers()
    {
        $result = [];
        foreach ($this->config->connectionNames() as $name) {
            $result[] = $this->driver($name);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function createInjection(\ReflectionClass $class, $context = null)
    {
        //If context is empty default database will be returned
        return $this->database($context);
    }
}
