<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ODM;

class MongoDatabase extends \MongoDB
{
    /**
     * This is magick constant used by Spiral Constant, it helps system to resolve controllable injections,
     * once set - Container will ask specific binding for injection.
     */
    const INJECTOR = ODM::class;

    /**
     * Profiling levels.
     */
    const PROFILE_SIMPLE  = 1;
    const PROFILE_EXPLAIN = 2;

    /**
     * ODMManager component.
     *
     * @invisible
     * @var ODM
     */
    protected $odm = null;

    /**
     * ODM database instance name/id.
     *
     * @var string
     */
    protected $name = '';

    /**
     * Connection configuration.
     *
     * @var array
     */
    protected $config = [
        'profiling' => self::PROFILE_SIMPLE
    ];

    /**
     * Mongo connection instance.
     *
     * @var \Mongo|\MongoClient
     */
    protected $connection = null;

    /**
     * New MongoDatabase instance.
     *
     * @param ODM    $odm    ODMManager component.
     * @param string $name   ODM database instance name/id.
     * @param array  $config Connection configuration.
     */
    public function __construct(ODM $odm, $name, array $config)
    {
        $this->odm = $odm;
        $this->name = $name;
        $this->config = $this->config + $config;

        //Selecting client
        if (class_exists('MongoClient', false))
        {
            $this->connection = new \MongoClient($this->config['server'], $this->config['options']);
        }
        else
        {
            $this->connection = new \Mongo($this->config['server'], $this->config['options']);
        }

        parent::__construct($this->connection, $this->config['database']);
    }

    /**
     * Internal database name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * While profiling enabled driver will create query logging and benchmarking events. This is
     * recommended option on development environment.
     *
     * @param bool $enabled Enable or disable driver profiling.
     * @return $this
     */
    public function profiling($enabled = true)
    {
        $this->config['profiling'] = $enabled;

        return $this;
    }

    /**
     * Check if profiling mode is enabled.
     *
     * @return bool
     */
    public function isProfiling()
    {
        return !empty($this->config['profiling']);
    }

    /**
     * Get database profiling level.
     *
     * @return int
     */
    public function getProfilingLevel()
    {
        return $this->config['profiling'];
    }

    /**
     * ODM collection instance for current db. ODMCollection has all the featured from MongoCollection,
     * but it will resolve results as ODM Document.
     *
     * @param string $name  Collection name.
     * @param array  $query Initial collection query.
     * @return Collection
     */
    public function odmCollection($name, array $query = [])
    {
        return new Collection($this->odm, $this->name, $name, $query);
    }
}