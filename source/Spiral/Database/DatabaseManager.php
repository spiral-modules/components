<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Database;

use Spiral\Core\Component;
use Spiral\Core\Container\InjectorInterface;
use Spiral\Core\Container\SingletonInterface;
use Spiral\Core\FactoryInterface;
use Spiral\Database\Configs\DatabasesConfig;
use Spiral\Database\Entities\Database;
use Spiral\Database\Entities\Driver;
use Spiral\Database\Exceptions\DatabaseException;
use Spiral\Database\Exceptions\DBALException;

class DatabaseManager extends Component implements SingletonInterface, InjectorInterface
{
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
    private $connections = [];

    /**
     * @var DatabasesConfig
     */
    protected $config = null;

    /**
     * @invisible
     *
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
     * Manually set database.
     *
     * @param Database $database
     * @return $this
     *
     * @throws DBALException
     */
    public function setDatabase(Database $database)
    {
        if (isset($this->databases[$database->getName()])) {
            throw new DBALException("Database '{$database->getName()}' already exists");
        }

        $this->databases[$database->getName()] = $database;

        return $this;
    }

    /**
     * Automatically create database instance based on given options and connection (in a form or
     * instance or alias).
     *
     * @param string        $name
     * @param string        $prefix
     * @param string|Driver $connection Connection name or instance.
     * @return Database
     *
     * @throws DBALException
     */
    public function registerDatabase($name, $prefix, $connection)
    {
        if (!$connection instanceof Driver) {
            $connection = $this->connection($connection);
        }

        $instance = $this->factory->make(Database::class, [
            'name'   => $name,
            'prefix' => $prefix,
            'driver' => $connection,
        ]);

        $this->setDatabase($instance);

        return $instance;
    }

    /**
     * Get Database associated with a given database alias or automatically created one.
     *
     * @param string|null $database
     *
     * @return Database
     *
     * @throws DBALException
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
            throw new DBALException(
                "Unable to create Database, no presets for '{$database}' found"
            );
        }

        //No need to benchmark here, due connection will happen later
        $instance = $this->factory->make(Database::class, [
            'name'   => $database,
            'prefix' => $this->config->databasePrefix($database),

            'driver' => $this->connection($this->config->databaseConnection($database)),
        ]);

        return $this->databases[$database] = $instance;
    }

    /**
     * Manually set connection instance.
     *
     * @param Driver $driver
     *
     * @return $this
     *
     * @throws DBALException
     */
    public function setConnection(Driver $driver)
    {
        if (isset($this->connections[$driver->getName()])) {
            throw new DBALException("Connection '{$driver->getName()}' already exists");
        }

        $this->connections[$driver->getName()] = $driver;

        return $this;
    }

    /**
     * Create and register connection under given name.
     *
     * @param string $name
     * @param string $driver Driver class.
     * @param string $dns
     * @param string $username
     * @param string $password
     * @return Driver
     */
    public function registerConnection($name, $driver, $dns, $username, $password = '')
    {
        $instance = $this->factory->make($driver, [
            'name'   => $name,
            'config' => [
                'connection' => $dns,
                'username'   => $username,
                'password'   => $password
            ]
        ]);

        $this->setConnection($instance);

        return $instance;
    }

    /**
     * Get connection/driver by it's name. Every driver associated with configured connection,
     * there is minor de-sync in naming due legacy code.
     *
     * @param string $connection
     *
     * @return Driver
     *
     * @throws DBALException
     */
    public function connection($connection)
    {
        if (isset($this->connections[$connection])) {
            return $this->connections[$connection];
        }

        if (!$this->config->hasConnection($connection)) {
            throw new DBALException(
                "Unable to create Driver, no presets for '{$connection}' found"
            );
        }

        $instance = $this->factory->make($this->config->connectionDriver($connection), [
            'name'   => $connection,
            'config' => $this->config->connectionConfig($connection),
        ]);

        return $this->connections[$connection] = $instance;
    }

    /**
     * Get instance of every available database.
     *
     * @return Database[]
     *
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
     *
     * @throws DatabaseException
     */
    public function getConnections()
    {
        $result = [];
        foreach ($this->config->connectionNames() as $name) {
            $result[] = $this->connection($name);
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