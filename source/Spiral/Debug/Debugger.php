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
use Spiral\Core\ConfiguratorInterface;
use Spiral\Core\Container\SingletonInterface;
use Spiral\Core\ContainerInterface;
use Spiral\Core\Traits\ConfigurableTrait;
use Spiral\Debug\Exceptions\BenchmarkException;

/**
 * Debugger is responsible for global log, benchmarking and configuring Monolog loggers.
 */
class Debugger extends Component implements
    BenchmarkerInterface,
    LogsInterface,
    SingletonInterface
{
    /**
     * Logger trait is required for Dumper to perform dump into debug log.
     */
    use ConfigurableTrait;

    /**
     * Declares to IoC that component instance should be treated as singleton.
     */
    const SINGLETON = self::class;

    /**
     * Configuration section.
     */
    const CONFIG = 'debug';

    /**
     * @invisible
     * @var array
     */
    private $benchmarks = [];

    /**
     * Container is needed to construct log handlers.
     *
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
        if (empty($this->config['logHandlers'][$name])) {
            return [];
        }

        $handlers = [];
        foreach ($this->config['logHandlers'][$name] as $handler) {
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