<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Debug;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\HandlerInterface;
use Monolog\Logger;
use Spiral\Core\Component;
use Spiral\Core\ConstructorInterface;
use Spiral\Core\Container\SingletonInterface;
use Spiral\Debug\Configs\DebuggerConfig;
use Spiral\Debug\Exceptions\BenchmarkException;

/**
 * Debugger is responsible for global log, benchmarking and configuring Monolog loggers.
 */
class Debugger extends Component implements BenchmarkerInterface, LogsInterface, SingletonInterface
{
    /**
     * Declares to IoC that component instance should be treated as singleton.
     */
    const SINGLETON = self::class;

    /**
     * @invisible
     * @var array
     */
    private $benchmarks = [];

    /**
     * @var HandlerInterface
     */
    protected $sharedHandler = null;

    /**
     * @var DebuggerConfig
     */
    protected $config = null;

    /**
     * Container is needed to construct log handlers.
     *
     * @invisible
     * @var ConstructorInterface
     */
    protected $constructor = null;

    /**
     * @param DebuggerConfig       $config
     * @param ConstructorInterface $constructor
     */
    public function __construct(DebuggerConfig $config, ConstructorInterface $constructor)
    {
        $this->config = $config;
        $this->constructor = $constructor;
    }

    /**
     * {@inheritdoc}
     */
    public function getLogger($name)
    {
        return new Logger($name, $this->logHandlers($name));
    }

    /**
     * Get handlers associated with specified log.
     *
     * @param string $name
     * @return HandlerInterface[]
     */
    public function logHandlers($name)
    {
        $handlers = [];
        if (!empty($this->sharedHandler)) {
            //Shared handler applied to every Logger
            $handlers[] = $this->sharedHandler;
        }

        if (!$this->config->hasHandlers($name)) {
            return $handlers;
        }

        foreach ($this->config->logHandlers($name) as $handler) {
            /**
             * @var HandlerInterface $instance
             */
            $handlers[] = $instance = $this->constructor->construct(
                $handler['handler'],
                $handler['options']
            );

            if (!empty($handler['format'])) {
                //Let's use custom line formatter
                $instance->setFormatter(new LineFormatter($handler['format']));
            }
        }

        return $handlers;
    }

    /**
     * Set instance of shared HandlerInterface, such handler will be passed to every created log.
     * To remove existed handler set it argument as null.
     *
     * @param HandlerInterface $handler
     */
    public function shareHandler(HandlerInterface $handler = null)
    {
        $this->sharedHandler = $handler;
    }

    /**
     * {@inheritdoc}
     *
     * @throws BenchmarkException
     */
    public function benchmark($caller, $record, $context = '')
    {
        $benchmarkID = count($this->benchmarks);
        if (is_array($record)) {
            $benchmarkID = $record[0];
        } elseif (!isset($this->benchmarks[$benchmarkID])) {
            $this->benchmarks[$benchmarkID] = [$caller, $record, $context, microtime(true)];

            //Payload
            return [$benchmarkID];
        }

        if (!isset($this->benchmarks[$benchmarkID])) {
            throw new BenchmarkException("Unpaired benchmark record '{$benchmarkID}'.");
        }

        $this->benchmarks[$benchmarkID][4] = microtime(true);

        return $this->benchmarks[$benchmarkID][4] - $this->benchmarks[$benchmarkID][3];
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