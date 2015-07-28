<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Views;

use Psr\Log\LoggerAwareInterface;
use Spiral\Core\ConfiguratorInterface;
use Spiral\Core\ContainerInterface;
use Spiral\Core\Traits\ConfigurableTrait;
use Spiral\Debug\Traits\LoggerTrait;
use Spiral\Files\FilesInterface;
use Spiral\Core\Singleton;

class ViewManager extends Singleton implements ViewsInterface, LoggerAwareInterface
{
    /**
     * Will provide us helper method getInstance().
     */
    use ConfigurableTrait, LoggerTrait;

    /**
     * Declares to IoC that component instance should be treated as singleton.
     */
    const SINGLETON = self::class;

    /**
     * Configuration section.
     */
    const CONFIG = 'views';

    /**
     * Default view namespace. View component can support as many namespaces and user want, to
     * specify names use render(namespace:view) syntax.
     */
    const DEFAULT_NAMESPACE = 'default';

    /**
     * Extension used to represent cached view files.
     */
    const CACHE_EXTENSION = 'php';

    /**
     * Container instance.
     *
     * @invisible
     * @var ContainerInterface
     */
    protected $container = null;

    /**
     * FileManager component.
     *
     * @invisible
     * @var FilesInterface
     */
    protected $files = null;

    /**
     * Registered view namespaces. Every namespace can include multiple search directories. Search
     * directory may have key which will be treated as namespace directory origin, this allows user
     * or template to include view from specified location, even if there is multiple directories under
     * view.
     *
     * @var array
     */
    protected $namespaces = [];

    /**
     * Variables used on compilation stage to define cached filename.
     *
     * @var array
     */
    protected $dependencies = [];

    /**
     * Constructing view component and initiating view namespaces, namespaces are used to find view
     * file destination and switch templates from one module to another.
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
        $this->config = $configurator->getConfig(static::CONFIG);

        $this->container = $container;
        $this->files = $files;

        //Mounting namespaces from config and external modules
        $this->namespaces = $this->config['namespaces'];
    }

    /**
     * Current view cache directory.
     *
     * @return string
     */
    public function getCacheDirectory()
    {
        return $this->config['caching']['directory'];
    }

    /**
     * All registered and available view namespaces.
     *
     * @return array|null
     */
    public function getNamespaces()
    {
        return $this->namespaces;
    }

    /**
     * Add view namespace directory.
     *
     * @param string $namespace
     * @param string $directory
     * @return $this
     */
    public function addNamespace($namespace, $directory)
    {
        if (!isset($this->namespaces[$namespace]))
        {
            $this->namespaces[$namespace] = [];
        }

        $this->namespaces[$namespace][] = $directory;

        return $this;
    }

    /**
     * Get view dependency variable by names.
     *
     * @param string $name
     * @param mixed  $default
     * @return string
     */
    public function getDependency($name, $default = null)
    {
        return array_key_exists($name, $this->dependencies)
            ? $this->dependencies[$name]
            : $default;
    }

    /**
     * Variables which will be applied on view caching and view processing stages, different variable
     * value will create different cache version. Usage example can be: layout redefinition, user
     * logged state and etc. You should never use this function for client or dynamic data.
     *
     * @param string $name
     * @param string $value
     * @return string
     */
    public function setDependency($name, $value)
    {
        $this->dependencies[$name] = $value;
    }

    /**
     * Get physical location of view filename (if ViewInterface allows that).
     *
     * @param string $namespace View namespace.
     * @param string $view      View filename, without .php included.
     * @param string $engine    Name of used engine.
     * @return string|null
     * @throws ViewException
     */
    public function viewFilename($namespace, $view, &$engine = null)
    {
        if (!isset($this->namespaces[$namespace]))
        {
            throw new ViewException("Undefined view namespace '{$namespace}'.");
        }

        /**
         * This section can be potentially cached in view runtime data to optimize
         * view detection process, it's not required at this moment... but possible.
         */
        foreach ($this->namespaces[$namespace] as $directory)
        {
            foreach ($this->config['engines'] as $engine => $options)
            {
                foreach ($options['extensions'] as $extension)
                {
                    $candidate = $directory . '/' . $view . '.' . $extension;
                    if ($this->files->exists($candidate))
                    {
                        return $this->files->normalizePath($candidate);
                    }
                }
            }
        }

        throw new ViewException("Unable to find view '{$view}' in namespace '{$namespace}'.");
    }

    /**
     * Get all views located in specified namespace. Will return list of view names associated with
     * appropriate engine.
     *
     * @param string $namespace
     * @return array
     */
    public function getViews($namespace)
    {
        if (!isset($this->namespaces[$namespace]))
        {
            throw new ViewException("Invalid view namespace '{$namespace}'.");
        }

        $result = [];
        foreach ($this->namespaces[$namespace] as $directory)
        {
            foreach ($this->files->getFiles($directory) as $filename)
            {
                $extension = $this->files->extension($filename);

                //Let's check if we have any engine to handle this type of file
                $foundEngine = false;
                foreach ($this->config['engines'] as $engine => $options)
                {
                    if (in_array($extension, $options['extensions']))
                    {
                        $foundEngine = $engine;
                    }
                }

                if (empty($foundEngine))
                {
                    //Not view file
                    continue;
                }

                //We can fetch view name (2 will remove ./)
                $view = substr(
                    $this->files->relativePath($filename, $directory),
                    2,
                    -1 * strlen($extension) - 1
                );

                $result[$view] = $foundEngine;
            }
        }

        return $result;
    }

    /**
     * Cached filename depends only on view name and provided set of "staticVariables", changing this
     * set system can cache some view content on file system level. For example view component can
     * set language variable, which will be rendering another view every time language changed and
     * allow to cache translated texts.
     *
     * @param string $namespace View namespace.
     * @param string $viewName  View filename, without php included.
     * @return string
     */
    public function cacheFilename($namespace, $viewName)
    {
        foreach ($this->config['dependencies'] as $variable => $provider)
        {
            $this->dependencies[$variable] = call_user_func([
                $this->container->get($provider[0]),
                $provider[1]
            ]);
        }

        $postfix = '-' . hash('crc32b', join(',', $this->dependencies)) . '.' . self::CACHE_EXTENSION;

        return $this->getCacheDirectory() . '/'
        . $namespace . '-' . trim(str_replace(['\\', '/'], '-', $viewName), '-')
        . $postfix;
    }

    /**
     * Check if compiled view cache expired and has to be re-rendered. You can disable view cache
     * by altering view config (this will slow your application dramatically but will simplyfy
     * development).
     *
     * @param string $viewFilename
     * @param string $cacheFilename
     * @return bool
     */
    protected function isExpired($viewFilename, $cacheFilename)
    {
        if (!$this->config['caching']['enabled'])
        {
            //Aways invalidate
            return true;
        }

        if (!$this->files->exists($cacheFilename))
        {
            return true;
        }

        return $this->files->timeUpdated($cacheFilename) < $this->files->timeUpdated($viewFilename);
    }

    /**
     * Return cached or not cached version of view. Automatically apply view processors to source.
     *
     * @param string $namespace  View namespace.
     * @param string $view       View filename, without php included.
     * @param bool   $compile    If true, view source will be processed using view processors before
     *                           saving to cache.
     * @param bool   $resetCache Force cache reset. Cache can be also disabled in view config.
     * @param string $engine     Name of used engine.
     * @return string
     * @throws ViewException
     */
    public function getFilename(
        $namespace,
        $view,
        $compile = true,
        $resetCache = false,
        &$engine = null
    )
    {
        $viewFilename = $this->viewFilename($namespace, $view, $engine);

        //Pre-compilation is possible only when engine defined compiler
        if ($compile && !empty($this->config['engines'][$engine]['compiler']))
        {
            //Cached filename
            $cacheFilename = $this->cacheFilename($namespace, $view);

            if ($resetCache || $this->isExpired($viewFilename, $cacheFilename))
            {
                //Saving compilation result to filename
                $this->files->write(
                    $cacheFilename,
                    $this->compile($engine, $this->files->read($viewFilename), $namespace, $view),
                    FilesInterface::RUNTIME,
                    true
                );
            }

            return $cacheFilename;
        }

        return $viewFilename;
    }

    /**
     * Get source of non compiled view file.
     *
     * @param string $namespace
     * @param string $view
     * @return string
     */
    public function getSource($namespace, $view)
    {
        return $this->files->read($this->viewFilename($namespace, $view));
    }

    /**
     * Get instance of CompilerInterface associated with provided source and view name.
     *
     * @param string $engine
     * @param string $source    Input view source.
     * @param string $namespace View namespace.
     * @param string $view      View filename, without php included.
     * @return CompilerInterface
     */
    public function compiler($engine, $source, $namespace, $view)
    {
        return $this->container->get($this->config['engines'][$engine]['compiler'], [
            'viewManager' => $this,
            'source'      => $source,
            'namespace'   => $namespace,
            'view'        => $view,
            'config'      => $this->config['engines'][$engine]
        ]);
    }

    /**
     * Generate view cache using defined compiler.
     *
     * @param string $engine
     * @param string $source    Input view source.
     * @param string $namespace View namespace.
     * @param string $view      View filename, without php included.
     * @return bool|string
     */
    protected function compile($engine, $source, $namespace, $view)
    {
        return $this->compiler($engine, $source, $namespace, $view)->compile();
    }

    /**
     * Get instance of View class binded to specified view filename. View file will can be selected
     * from specified namespace, or default namespace if not specified.
     *
     * Every view file will be pro-processed using view processors (also defined in view config) before
     * rendering, result of pre-processing will be stored in names cache file to speed-up future
     * renderings.
     *
     * Example or view names:
     * home                     - render home view from default namespace
     * namespace:home           - render home view from specified namespace
     *
     * @param string $view View name without .php extension, can include namespace prefix separated
     *                     by : symbol.
     * @param array  $data Array or view data, will be exported as local view variables, not available
     *                     in view processors.
     * @return ViewInterface
     */
    public function get($view, array $data = [])
    {
        $namespace = self::DEFAULT_NAMESPACE;
        if (strpos($view, self::NS_SEPARATOR) !== false)
        {
            list($namespace, $view) = explode(self::NS_SEPARATOR, $view);
        }

        //Compiled view source
        $filename = $this->getFilename($namespace, $view, true, false, $engine);

        //View representer
        $renderer = $this->config['engines'][$engine]['view'];

        return new $renderer($this, $filename, $data, $namespace, $view);
    }

    /**
     * Perform view file rendering. View file will can be selected from specified namespace, or
     * default namespace if not specified.
     *
     * View data has to be associated array and will be exported using extract() function and set of
     * local view variables, here variable name will be identical to array key.
     *
     * Every view file will be pro-processed using view processors (also defined in view config) before
     * rendering, result of pre-processing will be stored in names cache file to speed-up future
     * renderings.
     *
     * Example or view names:
     * home                     - render home view from default namespace
     * namespace:home           - render home view from specified namespace
     *
     * @param string $view View name without .php extension, can include namespace prefix separated
     *                     by : symbol.
     * @param array  $data Array or view data, will be exported as local view variables, not available
     *                     in view processors.
     * @return string
     */
    public function render($view, array $data = [])
    {
        return $this->get($view, $data)->render();
    }
}