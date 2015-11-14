<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Database;

use Spiral\Core\Component;
use Spiral\Core\ConfiguratorInterface;
use Spiral\Core\Container\InjectorInterface;
use Spiral\Core\ContainerInterface;
use Spiral\Core\Traits\ConfigurableTrait;
use Spiral\Database\Entities\Database;
use Spiral\Database\Entities\Driver;
use Spiral\Database\Exceptions\DatabaseException;

/**
 * DatabaseManager responsible for database creation, configuration storage and drivers factory.
 */
class DatabaseManager extends Component implements InjectorInterface, DatabasesInterface
{
    /**
     * Configuration.
     */
    use ConfigurableTrait;

    /**
     * Declares to Spiral IoC that component instance should be treated as singleton.
     */
    const SINGLETON = self::class;

    /**
     * Configuration section.
     */
    const CONFIG = 'database';

    /**
     * By default spiral will force time conversion into single timezone before storing in
     * database, it will help us to ensure that we have no problems with switching timezones and
     * save a lot of time while development (potentially).
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
     * @invisible
     * @var ContainerInterface
     */
    protected $container = null;

    /**
     * @param ConfiguratorInterface $configurator
     * @param ContainerInterface    $container
     */
    public function __construct(ConfiguratorInterface $configurator, ContainerInterface $container)
    {
        $this->config = $configurator->getConfig(static::CONFIG);
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     *
     * @return Database
     */
    public function database($database = null)
    {
        if (empty($database)) {
            $database = $this->config['default'];
        }

        while (isset($this->config['aliases'][$database])) {
            //Resolving database alias
            $database = $this->config['aliases'][$database];
        }

        if (isset($this->databases[$database])) {
            return $this->databases[$database];
        }

        if (!isset($this->config['databases'][$database])) {
            throw new DatabaseException(
                "Unable to create database, no presets for '{$database}' found."
            );
        }

        $config = $this->config['databases'][$database];

        //No need to benchmark here, due connection will happen later
        $this->databases[$database] = $this->container->construct(Database::class, [
            'name'        => $database,
            'driver'      => $this->driver($config['connection']),
            'tablePrefix' => isset($config['tablePrefix']) ? $config['tablePrefix'] : ''
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

        if (!isset($this->config['connections'][$connection])) {
            throw new DatabaseException(
                "Unable to create Driver, no presets for '{$connection}' found."
            );
        }

        $config = $this->config['connections'][$connection];

        $this->drivers[$connection] = $this->container->construct(
            $config['driver'], compact('name', 'config')
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
        foreach ($this->config['databases'] as $name => $config) {
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
        foreach ($this->config['connections'] as $name => $config) {
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