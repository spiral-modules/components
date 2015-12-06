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
use Spiral\Core\Container\SingletonInterface;
use Spiral\Core\FactoryInterface;
use Spiral\Debug\Configs\DebuggerConfig;
use Spiral\Debug\Exceptions\BenchmarkException;
use Spiral\Debug\Exceptions\DebuggerException;
use Spiral\Debug\Logger\SharedHandler;

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
     * @todo array of handlers?
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
     * @var FactoryInterface
     */
    protected $factory = null;

    /**
     * @param DebuggerConfig   $config
     * @param FactoryInterface $factory
     */
    public function __construct(DebuggerConfig $config, FactoryInterface $factory)
    {
        $this->config = $config;
        $this->factory = $factory;
    }

    /**
     * {@inheritdoc}
     */
    public function getLogger($name)
    {
        //Monolog by default
        return $this->factory->make(Logger::class, [
            'name'     => $name,
            'handlers' => $this->logHandlers($name)
        ]);
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
            $handlers[] = $instance = $this->factory->make(
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
     * @return SharedHandler|null Returns previously set handler.
     */
    public function shareHandler(HandlerInterface $handler = null)
    {
        $previous = $this->sharedHandler;
        $this->sharedHandler = $handler;

        return $previous;
    }

    /**
     * @return bool
     */
    public function hasSharedHandler()
    {
        return !empty($this->sharedHandler);
    }

    /**
     * Get currently associated shared handler.
     *
     * @return HandlerInterface
     * @throws DebuggerException When no handler being set.
     */
    public function getSharedHandler()
    {
        if (empty($this->sharedHandler)) {
            throw new DebuggerException("Unable to receive shared handler.");
        }

        return $this->sharedHandler;
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