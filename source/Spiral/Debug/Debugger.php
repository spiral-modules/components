<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Debug;

use Psr\Log\LoggerAwareInterface;
use Spiral\Core\ConfiguratorInterface;
use Spiral\Core\ContainerInterface;
use Spiral\Core\Singleton;
use Spiral\Core\Traits\ConfigurableTrait;
use Spiral\Debug\Traits\LoggerTrait;

class Debugger extends Singleton implements BenchmarkerInterface, LoggerAwareInterface
{
    /**
     * Few traits.
     */
    use ConfigurableTrait, LoggerTrait;

    /**
     * Declares to IoC that component instance should be treated as singleton.
     */
    const SINGLETON = self::class;

    /**
     * Configuration section.
     */
    const CONFIG = 'debug';

    /**
     * ContainerInterface used to create log handlers.
     *
     * @var ContainerInterface
     */
    protected $container = null;

    /**
     * List of recorded benchmarks.
     *
     * @var array
     */
    protected $benchmarks = [];

    /**
     * Constructing debug component. Debug is one of primary spiral component and will be available
     * for use in any environment and any application point. This is first initiated component in
     * application.
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
     * Configure logger handlers. I don't really want to connect Monolog - you can do it by youself.
     *
     * @param Logger $logger
     */
    public function configureLogger(Logger $logger)
    {
        if (!isset($this->config['loggers'][$logger->getName()]))
        {
            //Nothing to configure
            return;
        }

        foreach ($this->config['loggers'][$logger->getName()] as $logLevel => $handler)
        {
            $logger->setHandler($logLevel, $this->container->get($handler['class'], [
                'options' => $handler
            ]));
        }
    }

    /**
     * Benchmarks used to record long or important operations inside spiral components. Only time
     * will be recorded. Method should return elapsed time when record will be closed (same set of
     * arguments has to be provided).
     *
     * @param object $caller  Call initiator (used to de-group events).
     * @param string $record  Benchmark record name.
     * @param string $context Record context (if any).
     * @return bool|float
     */
    public function benchmark($caller, $record, $context = '')
    {
        //Unique called ID
        $callerID = is_object($caller) ? spl_object_hash($caller) : $caller;

        $name = $callerID . '|' . $record . '|' . $context;
        if (!isset($this->benchmarks[$name]))
        {
            $this->benchmarks[$name] = [$caller, $context, microtime(true)];

            return true;
        }

        $this->benchmarks[$name][3] = microtime(true);

        return $this->benchmarks[$name][3] - $this->benchmarks[$name][2];
    }

    /**
     * Retrieve all active and finished benchmark records.
     *
     * @return array|null
     */
    public function getBenchmarks()
    {
        return $this->benchmarks;
    }
}