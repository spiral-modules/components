<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Debug;

use Exception;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Spiral\Core\Component;
use Spiral\Core\ConfiguratorInterface;
use Spiral\Core\Traits\SaturateTrait;
use Spiral\Debug\Traits\LoggerTrait;
use Spiral\Files\FilesInterface;
use Spiral\Views\ViewsInterface;

/**
 * Spiral implementation of SnapshotInterface with ability to render exception explanation using
 * ViewsInterface.
 */
class Snapshot extends Component implements SnapshotInterface, LoggerAwareInterface
{
    /**
     * Additional constructor arguments.
     */
    use SaturateTrait, LoggerTrait;

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
    private $rendered = '';

    /**
     * @var array
     */
    protected $config = [];

    /**
     * @var FilesInterface
     */
    protected $files = null;

    /**
     * @var ViewsInterface
     */
    protected $views = null;

    /**
     * Snapshot constructor.
     *
     * @param Exception             $exception
     * @param LoggerInterface       $logger
     * @param ConfiguratorInterface $configurator
     * @param FilesInterface        $files
     * @param ViewsInterface        $views
     */
    public function __construct(
        Exception $exception,
        LoggerInterface $logger = null,
        ConfiguratorInterface $configurator = null,
        FilesInterface $files = null,
        ViewsInterface $views = null
    ) {
        $this->exception = $exception;
        $this->logger = $logger;

        //Snapshot configuration tells how to rotate files and etc
        $this->config = $configurator->getConfig(static::CONFIG);

        //We can use global container as fallback if no default values were provided
        $this->files = $this->saturate($files, FilesInterface::class);
        $this->views = $this->saturate($views, ViewsInterface::class);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return (new \ReflectionObject($this->exception))->getShortName();
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
        $this->logger()->error($this->getMessage());

        if (!$this->config['reporting']['enabled']) {
            //No need to record anything
            return;
        }

        //Snapshot filename
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
            $this->dropOldest($snapshots);
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
        if (!empty($this->rendered)) {
            return $this->rendered;
        }

        return $this->rendered = $this->views->render($this->config['view'], [
            'snapshot' => $this
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

    /**
     * Clean old snapshots.
     *
     * @param array $snapshots
     */
    private function dropOldest(array $snapshots)
    {
        $oldest = '';
        $oldestTimestamp = PHP_INT_MAX;
        foreach ($snapshots as $snapshot) {
            $snapshotTimestamp = $this->files->time($snapshot);

            if ($snapshotTimestamp < $oldestTimestamp) {
                $oldestTimestamp = $snapshotTimestamp;
                $oldest = $snapshot;
            }
        }

        $this->files->delete($oldest);
    }
}
