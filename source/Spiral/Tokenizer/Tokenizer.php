<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Tokenizer;

use Spiral\Tokenizer\Reflections\ReflectionFile;
use Spiral\Core\ConfiguratorInterface;
use Spiral\Core\HippocampusInterface;
use Spiral\Core\Loader;
use Spiral\Core\Singleton;
use Spiral\Core\Traits\ConfigurableTrait;
use Spiral\Debug\Traits\LoggerTrait;
use Spiral\Events\Event;
use Spiral\Files\FilesInterface;

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
     * To cache tokenizer class map.
     *
     * @invisible
     * @var HippocampusInterface
     */
    protected $runtime = null;

    /**
     * FileManager component to load files.
     *
     * @invisible
     * @var FilesInterface
     */
    protected $files = null;

    /**
     * Loader component instance.
     *
     * @invisible
     * @var Loader
     */
    protected $loader = null;

    /**
     * Cache of already processed file reflections, used to speed up lookup.
     *
     * @var array
     */
    protected $cache = [];

    /**
     * Tokenizer used by spiral to fetch list of available classes, their declarations and locations.
     * This class mostly used for indexing, orm and odm schemas and etc. Additionally this class has
     * ability to perform simple PHP code highlighting which can be used in ExceptionResponses and
     * snapshots.
     *
     * @param ConfiguratorInterface $configurator
     * @param HippocampusInterface  $runtime
     * @param FilesInterface        $file
     * @param Loader                $loader
     */
    public function __construct(
        ConfiguratorInterface $configurator,
        HippocampusInterface $runtime,
        FilesInterface $file,
        Loader $loader
    )
    {
        $this->config = $configurator->getConfig(static::CONFIG);

        $this->runtime = $runtime;
        $this->file = $file;
        $this->loader = $loader;

        foreach ($this->config['directories'] as &$directory)
        {
            $directory = $file->normalizePath($directory, true);
            unset($directory);
        }

        $this->cache = $this->runtime->loadData('tokenizer-reflections');
    }

    /**
     * Fetch PHP tokens for specified filename. String tokens should be automatically extended with their
     * type and line.
     *
     * @param string $filename
     * @return array
     */
    public function fetchTokens($filename)
    {
        $tokens = token_get_all($this->file->read($filename));

        $line = 0;
        foreach ($tokens as &$token)
        {
            if (isset($token[self::LINE]))
            {
                $line = $token[self::LINE];
            }

            if (!is_array($token))
            {
                $token = [$token, $token, $line];
            }

            unset($token);
        }

        return $tokens;
    }

    /**
     * Index all available files excluding and generate list of found classes with their names and
     * filenames. Unreachable classes or files with conflicts be skipped.
     *
     * This is SLOW method, should be used only for static analysis.
     *
     * Output format:
     * $result['CLASS_NAME'] = [
     *      'class'    => 'CLASS_NAME',
     *      'filename' => 'FILENAME',
     *      'abstract' => 'ABSTRACT_BOOL'
     * ]
     *
     * @param mixed  $parent    Class or interface should be extended. By default - null (all classes).
     *                          Parent will also be included to classes list as one of results.
     * @param string $namespace Only classes in this namespace will be retrieved, null by default
     *                          (all namespaces).
     * @param string $postfix   Only classes with such postfix will be analyzed, empty by default.
     * @return array
     */
    public function getClasses($parent = null, $namespace = null, $postfix = '')
    {
        $result = [];
        foreach ($this->availableFiles() as $filename)
        {
            $reflection = $this->reflectionFile($filename);

            if ($reflection->hasIncludes())
            {
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
     * Fetch targeted classes from file reflection.
     *
     * @param ReflectionFile $fileReflection Source file reflection.
     * @param mixed          $parent         Class or interface should be extended. By default - null
     *                                       (all classes).
     *                                       Parent will also be included to classes list as one of
     *                                       results.
     * @param string         $namespace      Only classes in this namespace will be retrieved, null
     *                                       by default (all namespaces).
     * @param string         $postfix        Only classes with such postfix will be analyzed, empty
     *                                       by default.
     * @return array
     */
    protected function fetchClasses(
        ReflectionFile $fileReflection,
        $parent = null,
        $namespace = null,
        $postfix = ''
    )
    {
        $namespace = ltrim($namespace, '\\');
        if (!empty($parent) && (is_object($parent) || is_string($parent)))
        {
            $parent = new \ReflectionClass($parent);
        }

        $this->loader->enable()->events()->addListener('notFound', $listener = function (Event $event)
        {
            //We want exception if class can not be loaded
            throw new TokenizerException("Class {$event->context()['class']} can not be loaded.");
        });

        $result = [];
        foreach ($fileReflection->getClasses() as $class)
        {
            if (!$this->isTargeted($class, $namespace, $postfix))
            {
                continue;
            }

            try
            {
                $reflection = new \ReflectionClass($class);

                if (!empty($parent))
                {
                    if ($parent->isTrait())
                    {
                        if (!in_array($parent->getName(), self::getTraits($class)))
                        {
                            continue;
                        }
                    }
                    else
                    {
                        if (
                            !$reflection->isSubclassOf($parent)
                            && $reflection->getName() != $parent->getName()
                        )
                        {
                            continue;
                        }
                    }
                }

                $result[$class] = [
                    'name'     => $reflection->getName(),
                    'filename' => $fileReflection->getFileName(),
                    'abstract' => $reflection->isAbstract()
                ];
            }
            catch (\Exception $exception)
            {
                $this->logger()->error(
                    "Unable to resolve class '{class}', error \"{message}\".",
                    [
                        'class'   => $class,
                        'message' => $exception->getMessage()
                    ]
                );
            }
        }

        $this->loader->events()->removeListener('notFound', $listener);

        return $result;
    }

    /**
     * Check if class targeted for analysis.
     *
     * @param string $class
     * @param string $namespace
     * @param string $postfix
     * @return bool
     */
    protected function isTargeted($class, $namespace, $postfix)
    {
        if (!empty($namespace) && strpos(ltrim($class, '\\'), $namespace) === false)
        {
            return false;
        }

        if (!empty($postfix) && substr($class, -1 * strlen($postfix)) != $postfix)
        {
            return false;
        }

        return true;
    }

    /**
     * List of files allowed by tokenizer white and black list.
     *
     * @return array
     */
    protected function availableFiles()
    {
        $result = [];
        foreach ($this->config['directories'] as $directory)
        {
            foreach ($this->file->getFiles($directory, ['php']) as $filename)
            {
                $filename = $this->file->normalizePath($filename);
                foreach ($this->config['exclude'] as $exclude)
                {
                    if (strpos($filename, $exclude) !== false)
                    {
                        continue 2;
                    }
                }

                $result[] = $filename;
            }
        }

        return $result;
    }

    /**
     * Get all class traits.
     *
     * @param string $class
     * @return array
     */
    public function getTraits($class)
    {
        $traits = [];

        while ($class)
        {
            $traits = array_merge(class_uses($class), $traits);
            $class = get_parent_class($class);
        }

        //Traits from traits
        foreach (array_flip($traits) as $trait)
        {
            $traits = array_merge(class_uses($trait), $traits);
        }

        return array_unique($traits);
    }

    /**
     * Get ReflectionFile for given filename, reflection can be used to retrieve list of declared
     * classes, interfaces, traits and functions, plus it can locate function usages.
     *
     * @param string $filename PHP filename.
     * @return ReflectionFile
     */
    public function reflectionFile($filename)
    {
        if (empty($this->cache))
        {
            $this->cache = $this->runtime->loadData('tokenizer-reflections');
        }

        $fileMD5 = $this->file->md5($filename);

        //Let's check if file already cached
        if (isset($this->cache[$filename]) && $this->cache[$filename]['md5'] == $fileMD5)
        {
            return new ReflectionFile($filename, $this, $this->cache[$filename]);
        }

        $reflection = new ReflectionFile($filename, $this);

        //Let's save to cache
        $this->cache[$filename] = ['md5' => $fileMD5] + $reflection->exportSchema();
        $this->runtime->saveData('tokenizer-reflections', $this->cache);

        return $reflection;
    }
}