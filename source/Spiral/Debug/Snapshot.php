<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Debug;

use Exception;
use Spiral\Core\Component;
use Spiral\Core\Container\SaturableInterface;
use Spiral\Core\ContainerInterface;
use Spiral\Files\FilesInterface;
use Spiral\Views\ViewInterface;
use Spiral\Views\ViewProviderInterface;

/**
 * Spiral implementation of SnapshotInterface with ability to render exception explanation using
 * views.
 */
class Snapshot extends Component implements SnapshotInterface, SaturableInterface, ViewInterface
{
    /**
     * Message format.
     */
    const MESSAGE = "{exception}: {message} in {file} at line {line}";

    /**
     * Part of debug configuration.
     */
    const CONFIG = 'snapshots';

    /**
     * @var \Exception
     */
    private $exception = null;

    /**
     * Rendered backtrace view, can be used in to save into file, send by email or show to client.
     *
     * @var string
     */
    private $renderCache = '';

    /**
     * @var array
     */
    protected $config = [];

    /**
     * @invisible
     * @var ContainerInterface
     */
    protected $container = null;

    /**
     * @var Debugger
     */
    protected $debugger = null;

    /**
     * @var FilesInterface
     */
    protected $files = null;

    /**
     * @var ViewProviderInterface
     */
    protected $views = null;

    /**
     * [@inheritdoc}
     */
    public function __construct(Exception $exception)
    {
        $this->exception = $exception;
    }

    /**
     * @param ContainerInterface    $container
     * @param Debugger              $debugger
     * @param FilesInterface        $files
     * @param ViewProviderInterface $views
     */
    public function init(
        ContainerInterface $container,
        Debugger $debugger,
        FilesInterface $files,
        ViewProviderInterface $views
    ) {
        $this->config = $debugger->config()[static::CONFIG];

        $this->container = $container;
        $this->debugger = $debugger;
        $this->files = $files;
        $this->views = $views;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        $reflection = new \ReflectionObject($this->exception);

        return $reflection->getShortName();
    }

    /**
     * {@inheritdoc}
     */
    public function getException()
    {
        return $this->exception;
    }

    /**
     * {@inheritdoc}
     */
    public function getClass()
    {
        return get_class($this->exception);
    }

    /**
     * {@inheritdoc}
     */
    public function getFile()
    {
        return $this->exception->getFile();
    }

    /**
     * {@inheritdoc}
     */
    public function getLine()
    {
        return $this->exception->getLine();
    }

    /**
     * {@inheritdoc}
     */
    public function getTrace()
    {
        return $this->exception->getTrace();
    }

    /**
     * {@inheritdoc}
     */
    public function getMessage()
    {
        return \Spiral\interpolate(static::MESSAGE, [
            'exception' => $this->getClass(),
            'message'   => $this->exception->getMessage(),
            'file'      => $this->getFile(),
            'line'      => $this->getLine()
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function report()
    {
        $this->debugger->logger()->error($this->getMessage());

        if (!$this->config['reporting']['enabled']) {
            //No need to record anything
            return;
        }

        $filename = \Spiral\interpolate($this->config['reporting']['filename'], [
            'date'      => date($this->config['reporting']['dateFormat'], time()),
            'exception' => $this->getName()
        ]);

        //Writing to hard drive
        $this->files->write(
            $this->config['reporting']['directory'] . '/' . $filename,
            $this->render(),
            FilesInterface::RUNTIME,
            true
        );

        $snapshots = $this->files->getFiles($this->config['reporting']['directory']);
        if (count($snapshots) > $this->config['reporting']['maxSnapshots']) {
            $oldestSnapshot = '';
            $oldestTimestamp = PHP_INT_MAX;
            foreach ($snapshots as $snapshot) {
                $snapshotTimestamp = $this->files->time($snapshot);
                if ($snapshotTimestamp < $oldestTimestamp) {
                    $oldestTimestamp = $snapshotTimestamp;
                    $oldestSnapshot = $snapshot;
                }
            }

            $this->files->delete($oldestSnapshot);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function describe()
    {
        return [
            'error'    => $this->getMessage(),
            'location' => [
                'file' => $this->getFile(),
                'line' => $this->getLine()
            ],
            'trace'    => $this->getTrace()
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function render()
    {
        if (!empty($this->renderCache)) {
            return $this->renderCache;
        }

        return $this->renderCache = $this->views->render($this->config['view'], [
            'snapshot'  => $this,
            'container' => $this->container,
            'debugger'  => $this->debugger
        ]);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        if (php_sapi_name() == 'cli') {
            return (string)$this->exception;
        }

        return $this->render();
    }
}