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
use Spiral\Core\Traits\ConfigurableTrait;
use Spiral\Database\Migrations\MigratorInterface;
use Spiral\Debug\Traits\BenchmarkTrait;
use Spiral\Core\Singleton;

class DatabaseManager extends Singleton implements InjectorInterface
{
    /**
     * Some traits.
     */
    use ConfigurableTrait, BenchmarkTrait;

    /**
     * Declares to Spiral IoC that component instance should be treated as singleton.
     */
    const SINGLETON = self::class;

    /**
     * Configuration section.
     */
    const CONFIG = 'database';

    /**
     * By default spiral will force all time conversion into single timezone before storing in
     * database, it will help us to ensure that we have to problems with switching timezones and
     * save a lot of time while development. :)
     */
    const DEFAULT_TIMEZONE = 'UTC';

    /**
     * ContainerInterface instance.
     *
     * @invisible
     * @var ContainerInterface
     */
    protected $container = null;

    /**
     * Constructed instances of DBAL databases.
     *
     * @var Database[]
     */
    protected $databases = [];

    /**
     * DBAL component instance, component is responsible for connections to various SQL databases and
     * their schema builders/describers.
     *
     * @param ConfiguratorInterface $configurator
     * @param ContainerInterface    $container
     */
    public function __construct(ConfiguratorInterface $configurator, ContainerInterface $container)
    {
        $this->config = $configurator->getConfig(static::CONFIG);
        $this->container = $container;
    }

    /**
     * Get global timezone name should be used to convert dates and timestamps. Function is static
     * for performance reasons. Right now timezone is hardcoded, but in future we can make it changeable.
     *
     * @return string
     */
    public static function defaultTimezone()
    {
        return static::DEFAULT_TIMEZONE;
    }

    /**
     * Get instance of dbal Database. Database class is high level abstraction at top of Driver.
     * Multiple databases can use same driver and be different by table prefix.
     *
     * @param string $database Internal database name or alias, declared in config.
     * @param array  $config   Forced database configuration.
     * @param Driver $driver   Forced driver instance.
     * @return Database
     * @throws DatabaseException
     */
    public function db($database = 'default', array $config = [], Driver $driver = null)
    {
        if (isset($this->config['aliases'][$database]))
        {
            $database = $this->config['aliases'][$database];
        }

        if (isset($this->databases[$database]))
        {
            return $this->databases[$database];
        }

        if (empty($config))
        {
            if (!isset($this->config['databases'][$database]))
            {
                throw new DatabaseException(
                    "Unable to create database, no presets for '{$database}' found."
                );
            }

            $config = $this->config['databases'][$database];
        }

        if (!$driver)
        {
            //Driver identifier can be fetched from connection string
            $driver = substr($config['connection'], 0, strpos($config['connection'], ':'));
            $driver = $this->container->get($this->config['drivers'][$driver], compact('config'));
        }

        $this->benchmark('database', $database);

        $this->databases[$database] = $this->container->get(Database::class, [
            'name'        => $database,
            'driver'      => $driver,
            'tablePrefix' => isset($config['tablePrefix']) ? $config['tablePrefix'] : ''
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
     * Get selected migrator. Migrator class specified in DBAL configuration.
     *
     * @return MigratorInterface
     */
    public function createMigrator()
    {
        return $this->container->get($this->config['migrator']);
    }
}