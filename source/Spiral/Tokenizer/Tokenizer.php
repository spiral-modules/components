<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Tokenizer;

use Spiral\Core\Components\Loader;
use Spiral\Core\ConfiguratorInterface;
use Spiral\Core\HippocampusInterface;
use Spiral\Core\Singleton;
use Spiral\Core\Traits\ConfigurableTrait;
use Spiral\Debug\Traits\LoggerTrait;
use Spiral\Events\Entities\Event;
use Spiral\Files\FilesInterface;
use Spiral\Tokenizer\Exceptions\ReflectionException;
use Spiral\Tokenizer\Exceptions\TokenizerException;
use Spiral\Tokenizer\Reflections\ReflectionFile;

/**
 * Default implementation of spiral tokenizer support while and blacklisted directories and etc.
 */
class Tokenizer extends Singleton implements TokenizerInterface
{
    /**
     * Required traits.
     */
    use ConfigurableTrait, LoggerTrait;

    /**
     * Declares to IoC that component instance should be treated as singleton.
     */
    const SINGLETON = self::class;

    /**
     * Configuration section.
     */
    const CONFIG = 'tokenizer';

    /**
     * Cache of already processed file reflections, used to speed up lookup.
     *
     * @var array
     */
    private $cache = [];

    /**
     * @invisible
     * @var HippocampusInterface
     */
    protected $memory = null;

    /**
     * @invisible
     * @var FilesInterface
     */
    protected $files = null;

    /**
     * @invisible
     * @var Loader
     */
    protected $loader = null;

    /**
     * New instance of Tokenizer.
     *
     * @param ConfiguratorInterface $configurator
     * @param HippocampusInterface  $runtime
     * @param FilesInterface        $files
     * @param Loader                $loader
     */
    public function __construct(
        ConfiguratorInterface $configurator,
        HippocampusInterface $runtime,
        FilesInterface $files,
        Loader $loader
    ) {
        $this->config = $configurator->getConfig(static::CONFIG);

        $this->memory = $runtime;
        $this->files = $files;
        $this->loader = $loader;

        foreach ($this->config['directories'] as &$directory) {
            $directory = $files->normalizePath($directory);
            unset($directory);
        }

        $this->cache = $this->memory->loadData('tokenizer');
    }

    /**
     * {@inheritdoc}
     */
    public function fetchTokens($filename)
    {
        $tokens = token_get_all($this->files->read($filename));

        $line = 0;
        foreach ($tokens as &$token) {
            if (isset($token[self::LINE])) {
                $line = $token[self::LINE];
            }

            if (!is_array($token)) {
                $token = [$token, $token, $line];
            }

            unset($token);
        }

        return $tokens;
    }

    /**
     * {@inheritdoc}
     *
     * @param string $namespace Only classes in this namespace will be retrieved, empty by default
     *                          (all namespaces).
     * @param string $postfix   Only classes with such postfix will be analyzed, empty by default.
     * @throws ReflectionException
     */
    public function getClasses($parent = null, $namespace = null, $postfix = '')
    {
        $result = [];
        foreach ($this->availableFiles() as $filename) {
            $reflection = $this->fileReflection($filename);

            if ($reflection->hasIncludes()) {
                $this->logger()->warning(
                    "File '{filename}' has includes and will be excluded from analysis.",
                    ['filename' => $filename]
                );

                continue;
            }

            //Fetching classes from file
            $result = array_merge($result, $this->fetchClasses(
                $reflection, $parent, $namespace, $postfix
            ));
        }

        return $result;
    }

    /**
     * Get every class trait (including traits used in parents).
     *
     * @param string $class
     * @return array
     */
    public function getTraits($class)
    {
        $traits = [];

        while ($class) {
            $traits = array_merge(class_uses($class), $traits);
            $class = get_parent_class($class);
        }

        //Traits from traits
        foreach (array_flip($traits) as $trait) {
            $traits = array_merge(class_uses($trait), $traits);
        }

        return array_unique($traits);
    }

    /**
     * Get ReflectionFile instance associated with given filename, reflection can be used to
     * retrieve list of declared classes, interfaces, traits and functions, plus it can locate
     * function usages.
     *
     * @param string $filename
     * @return ReflectionFile
     */
    public function fileReflection($filename)
    {
        if (empty($this->cache)) {
            $this->cache = $this->memory->loadData('tokenizer');
        }

        $fileMD5 = $this->files->md5($filename = $this->files->normalizePath($filename));

        //Let's check if file already cached
        if (isset($this->cache[$filename]) && $this->cache[$filename]['md5'] == $fileMD5) {
            return new ReflectionFile($this, $filename, $this->cache[$filename]);
        }

        $reflection = new ReflectionFile($this, $filename);

        //Let's save to cache
        $this->cache[$filename] = ['md5' => $fileMD5] + $reflection->exportSchema();
        $this->memory->saveData('tokenizer', $this->cache);

        return $reflection;
    }

    /**
     * Fetch targeted classes from file reflection.
     *
     * @param ReflectionFile $fileReflection
     * @param mixed          $parent
     * @param string         $namespace
     * @param string         $postfix
     * @return array
     * @throws ReflectionException
     */
    private function fetchClasses(
        ReflectionFile $fileReflection,
        $parent = null,
        $namespace = null,
        $postfix = ''
    ) {
        $namespace = ltrim($namespace, '\\');
        if (!empty($parent) && (is_object($parent) || is_string($parent))) {
            $parent = new \ReflectionClass($parent);
        }

        $this->loader->enable()->events()->listen('notFound', $listener = function (Event $event) {
            //We want exception if class can not be loaded
            throw new TokenizerException("Class {$event->context()['class']} can not be loaded.");
        });

        $result = [];
        foreach ($fileReflection->getClasses() as $class) {
            if (!$this->isTargeted($class, $namespace, $postfix)) {
                continue;
            }

            try {
                $reflection = new \ReflectionClass($class);

                if (!empty($parent)) {
                    if ($parent->isTrait()) {
                        if (!in_array($parent->getName(), $this->getTraits($class))) {
                            continue;
                        }
                    } else {
                        if (
                            !$reflection->isSubclassOf($parent)
                            && $reflection->getName() != $parent->getName()
                        ) {
                            continue;
                        }
                    }
                }

                $result[$class] = [
                    'name'     => $reflection->getName(),
                    'filename' => $fileReflection->getFileName(),
                    'abstract' => $reflection->isAbstract()
                ];
            } catch (\Exception $exception) {
                $this->logger()->error(
                    "Unable to resolve class '{class}', error \"{message}\".",
                    [
                        'class'   => $class,
                        'message' => $exception->getMessage()
                    ]
                );
            }
        }

        $this->loader->events()->remove('notFound', $listener);

        return $result;
    }

    /**
     * Check if class targeted for analysis by comparing namespaces and postfixes.
     *
     * @param string $class
     * @param string $namespace
     * @param string $postfix
     * @return bool
     */
    private function isTargeted($class, $namespace, $postfix)
    {
        if (!empty($namespace) && strpos(ltrim($class, '\\'), $namespace) === false) {
            return false;
        }

        if (!empty($postfix) && substr($class, -1 * strlen($postfix)) != $postfix) {
            return false;
        }

        return true;
    }

    /**
     * List of files allowed by tokenizer white and black list.
     *
     * @return array
     */
    private function availableFiles()
    {
        $result = [];
        foreach ($this->config['directories'] as $directory) {
            foreach ($this->files->getFiles($directory, ['php']) as $filename) {
                foreach ($this->config['exclude'] as $exclude) {
                    if (strpos($filename, $exclude) !== false) {
                        continue 2;
                    }
                }

                $result[] = $filename;
            }
        }

        return $result;
    }
}