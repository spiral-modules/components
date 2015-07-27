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
     */
    public function __construct(ConfiguratorInterface $configurator)
    {
        $this->config = $configurator->getConfig($this);
    }

    /**
     * Configure logger handlers.
     *
     * @param Logger $logger
     */
    public function configureLogger(Logger $logger)
    {
        //TODO: MUST BE IMPLEMENTED
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