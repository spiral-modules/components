<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Views\Compiler;

use Spiral\Core\Component;
use Spiral\Core\ContainerInterface;
use Spiral\Debug\Traits\BenchmarkTrait;
use Spiral\Views\CompilerInterface;
use Spiral\Views\ViewsInterface;

class Compiler extends Component implements CompilerInterface
{
    /**
     * Compilation benchmarks.
     */
    use BenchmarkTrait;

    /**
     * ViewsInterface component.
     *
     * @invisible
     * @var ViewsInterface
     */
    protected $views = null;

    /**
     * Container interface.
     *
     * @invisible
     * @var ContainerInterface
     */
    protected $container = null;

    /**
     * Configuration.
     *
     * @var array
     */
    protected $config = [];

    /**
     * Non compiled view source.
     *
     * @var string
     */
    protected $source = '';

    /**
     * View namespace.
     *
     * @var string
     */
    protected $namespace = '';

    /**
     * View name.
     *
     * @var string
     */
    protected $view = '';

    /**
     * View processors. Processors used to pre-process view source and save it to cache, in normal
     * operation mode processors will be called only once and never during user request.
     *
     * @var array|ProcessorInterface[]
     */
    protected $processors = [];

    /**
     * Instance of view compiler. Compilers used to pre-process view files for faster rendering in
     * runtime environment.
     *
     * @param ViewsInterface     $views
     * @param ContainerInterface $container
     * @param array              $config    Compiler configuration.
     * @param string             $source    Non-compiled source.
     * @param string             $namespace View namespace.
     * @param string             $view      View name.
     */
    public function __construct(
        ViewsInterface $views,
        ContainerInterface $container,
        array $config,
        $source,
        $namespace,
        $view
    )
    {
        $this->views = $views;
        $this->container = $container;

        $this->config = $config;
        $this->source = $source;

        $this->namespace = $namespace;
        $this->view = $view;
    }

    /**
     * Get associated view manager.
     *
     * @return ViewsInterface
     */
    public function getViews()
    {
        return $this->views;
    }

    /**
     * Active namespace.
     *
     * @return string
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * Active view name.
     *
     * @return string
     */
    public function getView()
    {
        return $this->view;
    }

    /**
     * Get non compiled view source.
     *
     * @return string
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * Get filename of non compiled view file.
     *
     * @return string
     */
    public function getFilename()
    {
        return $this->views->getFilename($this->namespace, $this->view);
    }

    /**
     * Clone method used to create separate instance of Compiler using same settings but associated
     * with another view.
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

    /**
     * Get list of all view processors.
     *
     * @return ProcessorInterface[]
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
                'viewManager' => $this->views,
                'compiler'    => $this,
                'options'     => $options
            ]);
        }

        return $this->processors;
    }

    /**
     * Compile original view file to plain php code.
     *
     * @return string
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
}