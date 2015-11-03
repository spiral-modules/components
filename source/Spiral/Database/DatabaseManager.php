<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Database;

use Spiral\Core\ConfiguratorInterface;
use Spiral\Core\Container\InjectorInterface;
use Spiral\Core\ContainerInterface;
use Spiral\Core\Singleton;
use Spiral\Core\Traits\ConfigurableTrait;
use Spiral\Database\Entities\Database;
use Spiral\Database\Entities\Driver;
use Spiral\Database\Exceptions\DatabaseException;

/**
 * DatabaseManager responsible for database creation, configuration storage and drivers factory.
 */
class DatabaseManager extends Singleton implements InjectorInterface, DatabasesInterface
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
     * save a lot of time while development. :)
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
     * Use third argument to link multiple databases to one driver.
     *
     * @param Driver $driver Custom driver.
     * @return Database
     */
    public function db($database = null, array $config = [], Driver $driver = null)
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
                throw new DatabaseException(
                    "Unable to create database, no presets for '{$database}' found."
                );
            }

            $config = $this->config['databases'][$database];
        }

        if (empty($driver)) {
            $driver = $this->driver($config['connection']);
        }

        //No need to benchmark here, due connection will happen later
        $this->databases[$database] = $this->container->construct(Database::class, [
            'name'        => $database,
            'driver'      => $driver,
            'tablePrefix' => isset($config['tablePrefix']) ? $config['tablePrefix'] : ''
        ]);

        return $this->databases[$database];
    }

    /**
     * Get driver by it's name.
     *
     * @param string $name
     * @param array  $config Custom driver options and class.
     * @return Driver
     * @throws DatabaseException
     */
    public function driver($name, array $config = [])
    {
        if (isset($this->drivers[$name])) {
            return $this->drivers[$name];
        }

        if (empty($config)) {
            if (!isset($this->config['connections'][$name])) {
                throw new DatabaseException(
                    "Unable to create Driver, no presets for '{$name}' found."
                );
            }

            $config = $this->config['connections'][$name];
        }

        $this->drivers[$name] = $this->container->construct(
            $config['driver'], compact('name', 'config')
        );

        return $this->drivers[$name];
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
            $result[] = $this->db($name);
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
    public function createInjection(\ReflectionClass $class, $context)
    {
        return $this->db($context);
    }
}