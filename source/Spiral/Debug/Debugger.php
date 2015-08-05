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

/**
 * Debugger is responsible for primary debug log, benchmarking and configuring spiral loggers.
 */
class Debugger extends Singleton implements BenchmarkerInterface, LoggerAwareInterface
{
    /**
     * Logger trait is required for Dumper to perform dump into debug log.
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
     * @var array
     */
    private $benchmarks = [];

    /**
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
     * Configure logger handlers.
     *
     * @param Logger $logger
     */
    public function configureLogger(Logger $logger)
    {
        if (!isset($this->config['loggers'][$logger->getName()])) {
            //Nothing to configure
            return;
        }

        foreach ($this->config['loggers'][$logger->getName()] as $logLevel => $handler) {
            $logger->setHandler($logLevel, $this->container->get($handler['class'], [
                'options' => $handler
            ]));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function benchmark($caller, $record, $context = '')
    {
        //Unique called ID
        $callerID = is_object($caller) ? spl_object_hash($caller) : $caller;

        $name = $callerID . '|' . $record . '|' . $context;
        if (!isset($this->benchmarks[$name])) {
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