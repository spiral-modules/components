<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
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
class DatabaseProvider extends Singleton implements InjectorInterface
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
     * Create specified or select default instance of Database with associated Driver instance.
     * Use third argument to link multiple databases to one driver.
     *
     * @param string $database
     * @param array  $config Custom db configuration.
     * @param Driver $driver Custom driver.
     * @return Database
     * @throws DatabaseException
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
            //Driver identifier can be fetched from connection string
            $driver = substr($config['connection'], 0, strpos($config['connection'], ':'));

            $driver = $this->container->get($this->config['drivers'][$driver], compact('config'));
        }

        //No need to benchmark here, due connection will happen later
        $this->databases[$database] = $this->container->get(Database::class, [
            'name'        => $database,
            'driver'      => $driver,
            'tablePrefix' => isset($config['tablePrefix']) ? $config['tablePrefix'] : ''
        ]);

        return $this->databases[$database];
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
     * {@inheritdoc}
     */
    public function createInjection(\ReflectionClass $class, \ReflectionParameter $parameter)
    {
        return $this->db($parameter->getName());
    }
}