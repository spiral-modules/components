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
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Spiral\Core\Component;
use Spiral\Core\Container\SingletonInterface;
use Spiral\Core\ContainerInterface;
use Spiral\Debug\Config\DebuggerConfig;
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
     * @var DebuggerConfig
     */
    protected $config = null;

    /**
     * Container is needed to construct log handlers.
     *
     * @invisible
     * @var ContainerInterface
     */
    protected $container = null;

    /**
     * @param DebuggerConfig     $config
     * @param ContainerInterface $container
     */
    public function __construct(DebuggerConfig $config, ContainerInterface $container)
    {
        $this->config = $config;
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function createLogger($name)
    {
        return new Logger($name, $this->getHandlers($name));
    }

    /**
     * Get handlers associated with specified log.
     *
     * @param string $name
     * @return HandlerInterface[]
     */
    public function getHandlers($name)
    {
        if (!$this->config->hasHandlers($name)) {
            return [];
        }

        $handlers = [];
        foreach ($this->config->logHandlers($name) as $handler) {
            /**
             * @var HandlerInterface $instance
             */
            $handlers[] = $instance = $this->container->construct(
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