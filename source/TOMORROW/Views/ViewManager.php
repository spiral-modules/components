<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Views;

use Spiral\Core\ConfiguratorInterface;
use Spiral\Core\ContainerInterface;
use Spiral\Core\Traits\ConfigurableTrait;
use Spiral\Files\FilesInterface;
use Spiral\Core\Singleton;
use Spiral\Views\Exceptions\ViewException;

/**
 * Default ViewsInterface implementation with ability to change cache versions via external value
 * dependencies. ViewManager support multiple namespaces and namespaces associated with multiple
 * folders.
 */
class ViewManager extends Singleton implements ViewsInterface
{
    /**
     * Configuration is required.
     */
    use ConfigurableTrait;

    /**
     * Declares to IoC that component instance should be treated as singleton.
     */
    const SINGLETON = self::class;

    /**
     * Configuration section.
     */
    const CONFIG = 'views';

    /**
     * Extension for compiled views.
     */
    const EXTENSION = 'php';

    /**
     * @invisible
     * @var ContainerInterface
     */
    protected $container = null;

    /**
     * @invisible
     * @var FilesInterface
     */
    protected $files = null;

    /**
     * Namespaces associated with their locations.
     *
     * @var array
     */
    protected $namespaces = [];

    /**
     * View cache file will depends on this set of values.
     *
     * @var array
     */
    protected $dependencies = [];

    /**
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

        //Namespaces can be edited in runtime
        $this->namespaces = $this->config['namespaces'];
    }

    /**
     * List of every view namespace associated with directories.
     *
     * @return array
     */
    public function getNamespaces()
    {
        return $this->namespaces;
    }

    /**
     * Create new namespace => location association.
     *
     * @param string $namespace
     * @param string $location
     * @return self
     */
    public function addNamespace($namespace, $location)
    {
        if (!isset($this->namespaces[$namespace]))
        {
            $this->namespaces[$namespace] = [];
        }

        $this->namespaces[$namespace][] = $location;

        return $this;
    }

    /**
     * Add new view dependency. Every new dependency will change generated cache filename.
     *
     * @param string $name
     * @param mixed  $value
     */
    public function setDependency($name, $value)
    {
        $this->dependencies[$name] = $value;
    }

    /**
     * Get dependency value or return null.
     *
     * @param string $name
     * @param mixed  $default
     * @return mixed
     */
    public function getDependency($name, $default = null)
    {
        return array_key_exists($name, $this->dependencies) ? $this->dependencies[$name] : $default;
    }

    /**
     * Get list of view names associated with specified namespace.
     *
     * @param string $namespace
     * @return array
     * @throws ViewException
     */
    public function getViews($namespace)
    {
        if (!isset($this->namespaces[$namespace]))
        {
            throw new ViewException("Invalid view namespace '{$namespace}'.");
        }

        $result = [];
        foreach ($this->namespaces[$namespace] as $location)
        {
            $location = $this->files->normalizePath($location);
            foreach ($this->files->getFiles($location) as $filename)
            {
                $foundEngine = false;
                foreach ($this->config['engines'] as $engine => $options)
                {
                    if (in_array($this->files->extension($filename), $options['extensions']))
                    {
                        $foundEngine = $engine;
                        break;
                    }
                }

                if (empty($foundEngine))
                {
                    //No engines found = not view
                    continue;
                }

                //View filename without extension
                $filename = substr($filename, 0, -1 - strlen($this->files->extension($filename)));
                $name = substr($filename, strlen($location) + strlen(FilesInterface::SEPARATOR));

                $result[$name] = $foundEngine;
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @param string $engine Engine name associated with found view (reference).
     */
    public function getFilename($namespace, $view, &$engine = null)
    {
        if (!isset($this->namespaces[$namespace]))
        {
            throw new ViewException("Undefined view namespace '{$namespace}'.");
        }

        //This part better be cached one dat
        foreach ($this->namespaces[$namespace] as $directory)
        {
            foreach ($this->config['engines'] as $engine => $options)
            {
                foreach ($options['extensions'] as $extension)
                {
                    $candidate = $directory . FilesInterface::SEPARATOR . $view . '.' . $extension;
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
     * {@inheritdoc}
     */
    public function getSource($namespace, $view)
    {
        return $this->files->read($this->getFilename($namespace, $view));
    }


    public function compile($namespace, $view)
    {
    }

    //    /**
    //     * {@inheritdoc}
    //     */
    //    public function getFilename(
    //        $namespace,
    //        $view,
    //        $compile = true,
    //        $resetCache = false,
    //        &$engine = null
    //    )
    //    {
    //        $viewFilename = $this->getFilename($namespace, $view, $engine);
    //
    //        //Pre-compilation is possible only when engine defined compiler
    //        if ($compile && !empty($this->config['engines'][$engine]['compiler']))
    //        {
    //            //Cached filename
    //            $cacheFilename = $this->cacheFilename($namespace, $view);
    //
    //            if ($resetCache || $this->isExpired($viewFilename, $cacheFilename))
    //            {
    //                //Saving compilation result to filename
    //                $this->files->write(
    //                    $cacheFilename,
    //                    $this->compile($engine, $this->files->read($viewFilename), $namespace, $view),
    //                    FilesInterface::RUNTIME,
    //                    true
    //                );
    //            }
    //
    //            return $cacheFilename;
    //        }
    //
    //        return $viewFilename;
    //    }

    /**
     * {@inheritdoc}
     */
    protected function compile($engine, $source, $namespace, $view)
    {
        return $this->compiler($engine, $source, $namespace, $view)->compile();
    }

    /**
     * {@inheritdoc}
     */
    public function get($path, array $data = [])
    {
        $namespace = self::DEFAULT_NAMESPACE;
        if (strpos($path, self::NS_SEPARATOR) !== false)
        {
            list($namespace, $path) = explode(self::NS_SEPARATOR, $path);
        }

        //Compiled view source
        $filename = $this->getFilename($namespace, $path, true, false, $engine);

        //View representer
        $renderer = $this->config['engines'][$engine]['view'];

        return new $renderer($this, $namespace, $path, $data, $filename);
    }

    /**
     * {@inheritdoc}
     */
    public function render($path, array $data = [])
    {
        return $this->get($path, $data)->render();
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

        return $this->config['caching']['directory'] . '/'
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

        return $this->files->time($cacheFilename) < $this->files->time($viewFilename);
    }
}