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
use Spiral\Core\Exceptions\Container\ContainerException;
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
     * Namespaces associated with their locations.
     *
     * @var array
     */
    private $namespaces = [];

    /**
     * View cache file will depends on this set of values.
     *
     * @var array
     */
    private $dependencies = [];

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

    /**
     * {@inheritdoc}
     */
    public function compile($namespace, $view)
    {
        //Via helper function
        $this->compiledFilename($namespace, $view, true);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $class Custom View implementation.
     * @throws ContainerException
     */
    public function get($path, array $data = [], $class = null)
    {
        $namespace = self::DEFAULT_NAMESPACE;
        if (strpos($path, self::NS_SEPARATOR) !== false)
        {
            list($namespace, $path) = explode(self::NS_SEPARATOR, $path);
        }

        $compiledFilename = $this->compiledFilename($namespace, $path, true, $engine);

        return $this->container->get(
            !empty($class) ? $class : $this->config['engines'][$engine]['view'],
            [
                'views'            => $this,
                'namespace'        => $namespace,
                'view'             => $path,
                'compiledFilename' => $compiledFilename
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function render($path, array $data = [])
    {
        return $this->get($path, $data)->render();
    }

    /**
     * Create filename where compiled version of view will be stored. Should use view dependencies.
     *
     * @param string $namespace
     * @param string $view
     * @return string
     */
    public function cacheFilename($namespace, $view)
    {
        foreach ($this->config['dependencies'] as $variable => $provider)
        {
            $this->dependencies[$variable] = call_user_func(
                [$this->container->get($provider[0]), $provider[1]]
            );
        }

        $postfix = '-' . hash('crc32b', join(',', $this->dependencies)) . '.' . self::EXTENSION;

        return $this->config['caching']['directory'] . '/' . $namespace . '-'
        . trim(str_replace(['\\', '/'], '-', $view), '-') . $postfix;
    }

    /**
     * Create engine specific compiler instance.
     *
     * @param string $engine
     * @param string $namespace
     * @param string $view
     * @return CompilerInterface
     * @throws ViewException
     * @throws ContainerException
     */
    protected function compiler($engine, $namespace, $view)
    {
        if (isset($this->config['engines'][$engine]))
        {
            throw new ViewException("Undefined view engine '{$engine}'.");
        }

        return $this->container->get($this->config['engines'][$engine]['compiler'], [
            'views'     => $this,
            'config'    => $this->config['engines'][$engine],
            'namespace' => $namespace,
            'view'      => $view
        ]);
    }

    /**
     * Return location of compiled view filename.
     *
     * @param string $namespace
     * @param string $view
     * @param bool   $reset
     * @param string $engine
     * @return string
     */
    protected function compiledFilename($namespace, $view, $reset = false, &$engine = null)
    {
        $viewFilename = $this->getFilename($namespace, $view, $engine);
        if (empty($this->config['engines'][$engine]['compiler']))
        {
            //Nothing to compile
            return $viewFilename;
        }

        $cacheFilename = $this->cacheFilename($namespace, $view);
        if ($reset || $this->isExpired($viewFilename, $cacheFilename))
        {
            $this->files->write(
                $cacheFilename,
                $this->compiler($engine, $namespace, $view)->compile(),
                FilesInterface::RUNTIME,
                true
            );
        }

        return $cacheFilename;
    }

    /**
     * Check if view cache has been expired.
     *
     * @param string $viewFilename
     * @param string $cacheFilename
     * @return bool
     */
    protected function isExpired($viewFilename, $cacheFilename)
    {
        if (!$this->config['caching']['enabled'])
        {
            return true;
        }

        if (!$this->files->exists($cacheFilename))
        {
            return true;
        }

        return $this->files->time($cacheFilename) < $this->files->time($viewFilename);
    }
}