<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Views;

use Spiral\Core\Component;
use Spiral\Core\ContainerInterface;
use Spiral\Core\Exceptions\Container\ContainerException;
use Spiral\Core\Traits\ConfigurableTrait;
use Spiral\Debug\Traits\BenchmarkTrait;
use Spiral\Views\Compiler\ProcessorInterface;
use Spiral\Views\Exceptions\ViewException;

/**
 * Default spiral compiler implementation.
 */
class Compiler extends Component implements CompilerInterface
{
    /**
     * Configuration and compilation benchmarks.
     */
    use ConfigurableTrait, BenchmarkTrait;

    /**
     * @invisible
     * @var ViewManager
     */
    protected $views = null;

    /**
     * @invisible
     * @var ContainerInterface
     */
    protected $container = null;

    /**
     * @var string
     */
    protected $namespace = '';

    /**
     * @var string
     */
    protected $view = '';

    /**
     * @var string
     */
    protected $source = '';

    /**
     * Chain of view processors to be applied to view source.
     *
     * @var array|ProcessorInterface[]
     */
    protected $processors = [];

    /**
     * {@inheritdoc}
     */
    public function __construct(
        ViewsInterface $views,
        ContainerInterface $container,
        array $config,
        $namespace,
        $view
    )
    {
        $this->config = $config;

        $this->views = $views;
        $this->container = $container;

        $this->namespace = $namespace;
        $this->view = $view;

        $this->source = $views->getSource($this->namespace, $this->view);
    }

    /**
     * {@inheritdoc}
     */
    public function compile()
    {
        $source = $this->source;
        foreach ($this->getProcessors() as $processor)
        {
            $reflection = new \ReflectionClass($processor);

            $context = $this->namespace . ViewsInterface::NS_SEPARATOR . $this->view;

            $this->benchmark($reflection->getShortName(), $context);
            $source = $processor->process($source);
            $this->benchmark($reflection->getShortName(), $context);
        }

        return $source;
    }

    /**
     * @return string
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * @return string
     */
    public function getView()
    {
        return $this->view;
    }

    /**
     * @return string
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * Get location of non compiler view file.
     *
     * @return string
     * @throws ViewException
     */
    public function getFilename()
    {
        return $this->views->getFilename($this->namespace, $this->view);
    }

    /**
     * List of every compiler processor.
     *
     * @return ProcessorInterface[]
     * @throws ContainerException
     */
    public function getProcessors()
    {
        if (!empty($this->processors))
        {
            return $this->processors;
        }

        foreach ($this->config['processors'] as $processor => $options)
        {
            $this->processors[] = $this->container->get($processor, [
                'views'    => $this->views,
                'compiler' => $this,
                'options'  => $options
            ]);
        }

        return $this->processors;
    }

    /**
     * Clone compiler with reconfigured namespace and view.
     *
     * @param string $namespace
     * @param string $view
     * @return Compiler
     */
    public function cloneCompiler($namespace, $view)
    {
        $compiler = clone $this;

        $compiler->namespace = $namespace;
        $compiler->view = $view;

        //We are getting new view source
        $compiler->source = $this->views->getSource($namespace, $view);

        //Processors has to be regenerated to flush content
        $compiler->processors = [];

        return $compiler;
    }
}