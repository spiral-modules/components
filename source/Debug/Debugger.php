<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Debug;

use Spiral\Core\ConfiguratorInterface;
use Spiral\Core\ContainerInterface;
use Spiral\Core\Singleton;
use Spiral\Core\Traits\ConfigurableTrait;
use Spiral\Debug\Traits\LoggerTrait;
use Spiral\Events\Traits\EventsTrait;
use Spiral\Files\FilesInterface;

class Debugger extends Singleton
{
    /**
     * Few traits.
     */
    use ConfigurableTrait, LoggerTrait, EventsTrait;

    /**
     * Declares to IoC that component instance should be treated as singleton.
     */
    const SINGLETON = self::class;

    /**
     * Container instance is required to resolve dependencies.
     *
     * @var ContainerInterface
     */
    protected $container = null;

    /**
     * File component.
     *
     * @var FilesInterface
     */
    protected $files = null;

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
     * @param FilesInterface        $files
     */
    public function __construct(
        ConfiguratorInterface $configurator,
        ContainerInterface $container,
        FilesInterface $files
    )
    {
        $this->config = $configurator->getConfig($this);
        $this->container = $container;
        $this->files = $files;
    }

    /**
     * Create logger associated with specified container.
     *
     * @param string $name
     * @return Logger
     */
    public function createLogger($name)
    {
        $logger = new Logger($name);

        if (isset($this->config['loggers'][$name]))
        {
            //TODO: Configuring logger
        }

        return $logger;
    }

    /**
     * Handle exception and convert it to user friendly snapshot instance.
     *
     * @param \Exception $exception
     * @param bool       $logError If true (default), message to error log will be added.
     * @return Snapshot
     */
    public function createSnapshot(\Exception $exception, $logError = true)
    {
        /**
         * @var Snapshot $snapshot
         */
        $snapshot = $this->container->get(Snapshot::class, [
            'exception' => $exception,
            'view'      => $this->config['snapshots']['view']
        ]);

        //Error message should be added to log only for non http exceptions
        if ($logError)
        {
            $this->logger()->error($snapshot->getMessage());
        }

        $filename = null;
        if ($this->config['snapshots']['enabled'])
        {
            //We can additionally save exception on hard drive
            $filename = \Spiral\interpolate($this->config['snapshots']['filename'], [
                'timestamp' => date($this->config['snapshots']['timeFormat'], time()),
                'exception' => $snapshot->shortName()
            ]);

            $this->files->write($filename, $snapshot->render());
        }

        $this->fire('snapshot', compact('snapshot', 'filename'));

        return $snapshot;
    }

    /**
     * Benchmarks used to record duration of long or memory inefficient operations in spiral, you
     * can use profiler panel to view benchmarks later.
     *
     * Every additional record name will be joined with caller name.
     *
     * @param string       $caller Caller name
     * @param string|array $record Record name(s).
     * @return bool|float
     */
    public function benchmark($caller, $record)
    {

        if (is_array($record))
        {
            //Formatting in form caller|record|recordB
            $name = $caller . '|' . join('|', $record);
        }
        else
        {
            $name = join('|', func_get_args());
        }

        if (!isset($this->benchmarks[$name]))
        {
            $this->benchmarks[$name] = [microtime(true), memory_get_usage()];

            return true;
        }

        $this->benchmarks[$name][] = microtime(true);
        $this->benchmarks[$name][] = memory_get_usage();

        return $this->benchmarks[$name][2] - $this->benchmarks[$name][0];
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