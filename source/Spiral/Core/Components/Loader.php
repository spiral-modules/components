<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Core\Components;

use Spiral\Core\HippocampusInterface;
use Spiral\Core\Singleton;
use Spiral\Events\Traits\EventsTrait;

/**
 * Can speed up class loading in some conditions. In addition this class is needed for tokenizer
 * to handle not found classes.
 */
class Loader extends Singleton
{
    /**
     * Event if class not found.
     */
    use EventsTrait;

    /**
     * Declares to IoC that component instance should be treated as singleton.
     */
    const SINGLETON = self::class;

    /**
     * List of classes loaded while this working session.
     *
     * @var array
     */
    private $classes = [];

    /**
     * Name of memory sections to use.
     *
     * @var string
     */
    private $name = 'loadmap';

    /**
     * Association between class and it's location.
     *
     * @var array
     */
    private $loadmap = [];

    /**
     * Is SPL methods handled.
     *
     * @var bool
     */
    private $enabled = false;

    /**
     * @var HippocampusInterface
     */
    protected $memory = null;

    /**
     * Loader will automatically handle SPL autoload functions to start caching loadmap.
     *
     * @param HippocampusInterface $memory
     */
    public function __construct(HippocampusInterface $memory)
    {
        $this->memory = $memory;
        $this->enable();
    }

    /**
     * Handle SPL auto location, will go to top of spl chain.
     *
     * @return $this
     */
    public function enable()
    {
        if (!$this->enabled) {
            if ((!$this->loadmap = $this->memory->loadData($this->name)) || !is_array($this->loadmap)) {
                $this->loadmap = [];
            }

            spl_autoload_register([$this, 'loadClass'], true, true);
            $this->enabled = true;
        }

        return $this;
    }

    /**
     * Stop handling SPL calls.
     *
     * @return $this
     */
    public function disable()
    {
        $this->enabled && spl_autoload_unregister([$this, 'loadClass']);
        $this->enabled = false;

        return $this;
    }

    /**
     * Re-enable autoload to push it up into food chain.
     */
    public function reset()
    {
        $this->disable()->enable();
    }

    /**
     * Memory section to use.
     *
     * @param string $name
     */
    public function setName($name)
    {
        if ($this->name != $name) {
            if (empty($this->loadmap = $this->memory->loadData($name)) || !is_array($this->loadmap)) {
                $this->loadmap = [];
            }
        }

        $this->name = $name;
    }

    /**
     * Find class declaration and load it.
     *
     * @param string $class Class name with namespace included.
     * @event notFound($class)
     */
    public function loadClass($class)
    {
        if (isset($this->loadmap[$class])) {
            try {
                //We already know route to class declaration
                include_once($this->classes[$class] = $this->loadmap[$class]);
            } catch (\ErrorException $exception) {
                //File was replaced or removed
                unset($this->loadmap[$class]);
                $this->memory->saveData($this->name, $this->loadmap);

                //Try to update route to class
                $this->loadClass($class);
            }

            return;
        }

        //Composer and other loaders.
        foreach (spl_autoload_functions() as $function) {
            if ($function instanceof \Closure || $function[0] != $this) {
                call_user_func($function, $class);

                if (class_exists($class, false) || interface_exists($class,
                        false) || trait_exists($class, false)
                ) {
                    //Class has been successfully found by external loader
                    //External loader are not going to provide us any information about class
                    //location, let's get it via Reflection
                    $reflector = new \ReflectionClass($class);

                    try {
                        $filename = str_replace('\\', '/', $reflector->getFileName());
                        $filename = rtrim(str_replace('//', '/', $filename), '/');

                        //Direct access to filesystem, yep.
                        if (file_exists($filename)) {
                            $this->loadmap[$class] = $this->classes[$class] = $filename;
                            $this->memory->saveData($this->name, $this->loadmap);
                        }
                    } catch (\ErrorException $exception) {
                        //getFileName can throw and exception, we can ignore it
                    }

                    return;
                }
            }
        }

        $this->fire('notFound', compact('class'));
    }

    /**
     * All loaded classes.
     *
     * @return array
     */
    public function getClasses()
    {
        return $this->classes;
    }

    /**
     * Check if desired class exists in loadmap.
     *
     * @param string $class
     * @return bool
     */
    public function isKnown($class)
    {
        return isset($this->classes[$class]);
    }
}