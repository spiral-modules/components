<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\ODM\Entities;

use Spiral\Core\Container\InjectableInterface;
use Spiral\ODM\MongoManager;

/**
 * Simple spiral ODM wrapper at top of MongoDB.
 */
class MongoDatabase extends \MongoDB implements InjectableInterface
{
    /**
     * This is magick constant used by Spiral Container, it helps system to resolve controllable
     * injections.
     */
    const INJECTOR = MongoManager::class;

    /**
     * Profiling levels. Not identical to MongoDB profiling levels.
     */
    const PROFILE_DISABLED = false;
    const PROFILE_SIMPLE   = 1;
    const PROFILE_EXPLAIN  = 2;

    /**
     * @var string
     */
    private $name = '';

    /**
     * @var \Mongo|\MongoClient
     */
    private $connection = null;

    /**
     * @var array
     */
    protected $config = ['profiling' => self::PROFILE_DISABLED];

    /**
     * @param string $name
     * @param array  $config Config must include sections 'server', 'options' and 'database'.
     */
    public function __construct($name, array $config)
    {
        $this->name = $name;
        $this->config = $config + $this->config;

        //Selecting client
        if (class_exists('MongoClient', false)) {
            $this->connection = new \MongoClient($this->config['server'], $this->config['options']);
        } else {
            $this->connection = new \Mongo($this->config['server'], $this->config['options']);
        }

        parent::__construct($this->connection, $this->config['database']);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * While profiling enabled driver will create query logging and benchmarking events. This is
     * recommended option in* development environments. Profiling will be applied for ODM Collection
     * queries only.
     *
     * @param bool|int $profiling Enable or disable driver profiling.
     *
     * @return $this
     */
    public function setProfiling($profiling = self::PROFILE_SIMPLE)
    {
        $this->config['profiling'] = $profiling;

        return $this;
    }

    /**
     * Check if profiling mode is enabled.
     *
     * @return bool
     */
    public function isProfiling()
    {
        return $this->config['profiling'] != self::PROFILE_DISABLED;
    }

    /**
     * Get database profiling level. Not identical to getProfilingLevel(). Used in document
     * selector.
     *
     * @see DocumentSelector
     * @return int
     */
    public function getProfiling()
    {
        return $this->config['profiling'];
    }
}