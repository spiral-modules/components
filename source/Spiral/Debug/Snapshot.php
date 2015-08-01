<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Debug;

use Spiral\Core\Component;
use Exception;
use Spiral\Core\Container\SaturableInterlace;
use Spiral\Core\ContainerInterface;
use Spiral\Files\FilesInterface;
use Spiral\Tokenizer\Hightligher;
use Spiral\Views\ViewsInterface;

/**
 * Spiral implementation of SnapshotInterface with ability to render exception explanation using views.
 */
class Snapshot extends Component implements SnapshotInterface, SaturableInterlace
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
     * @var ViewsInterface
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
     * @param ContainerInterface $container
     * @param Debugger           $debugger
     * @param FilesInterface     $files
     * @param ViewsInterface     $views
     */
    public function saturate(
        ContainerInterface $container,
        Debugger $debugger,
        FilesInterface $files,
        ViewsInterface $views)
    {
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

        if (!$this->config['reporting']['enabled'])
        {
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
        if (!empty($this->renderCache))
        {
            return $this->renderCache;
        }

        return $this->renderCache = $this->views->render($this->config['view'], [
            'dumpArguments' => $this->config['dumps'],
            'snapshot'      => $this,
            'container'     => $this->container
        ]);
    }
}