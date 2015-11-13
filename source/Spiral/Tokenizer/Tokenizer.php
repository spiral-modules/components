<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Tokenizer;

use Spiral\Core\Component;
use Spiral\Core\ConfiguratorInterface;
use Spiral\Core\Container\InjectorInterface;
use Spiral\Core\Container\SingletonInterface;
use Spiral\Core\HippocampusInterface;
use Spiral\Core\Loader;
use Spiral\Core\Traits\ConfigurableTrait;
use Spiral\Debug\Traits\LoggerTrait;
use Spiral\Files\FilesInterface;
use Spiral\Tokenizer\Reflections\ReflectionFile;
use Symfony\Component\Finder\Finder;

/**
 * Default implementation of spiral tokenizer support while and blacklisted directories and etc.
 */
class Tokenizer extends Component implements SingletonInterface, TokenizerInterface, InjectorInterface
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
     * Configuration section (used by Class locator).
     */
    const CONFIG = 'tokenizer';

    /**
     * Memory section.
     */
    const MEMORY = 'tokenizer';

    /**
     * Cache of already processed file reflections, used to speed up lookup.
     *
     * @var array
     */
    private $cache = [];

    /**
     * @invisible
     * @var FilesInterface
     */
    protected $files = null;

    /**
     * @invisible
     * @var HippocampusInterface
     */
    protected $memory = null;

    /**
     * Tokenizer constructor.
     *
     * @param FilesInterface        $files
     * @param ConfiguratorInterface $configurator
     * @param HippocampusInterface  $runtime
     */
    public function __construct(
        FilesInterface $files,
        ConfiguratorInterface $configurator,
        HippocampusInterface $runtime
    ) {
        $this->files = $files;
        $this->memory = $runtime;

        //Configuration is required for ClassLocator directories and excludes
        $this->config = $configurator->getConfig(static::CONFIG);
        $this->cache = $this->memory->loadData(static::MEMORY);
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
     */
    public function fileReflection($filename)
    {
        $fileMD5 = $this->files->md5($filename = $this->files->normalizePath($filename));

        if (isset($this->cache[$filename]) && $this->cache[$filename]['md5'] == $fileMD5) {
            //We can speed up reflection via tokenization cache
            return new ReflectionFile($filename, $this, $this->cache[$filename]);
        }

        $reflection = new ReflectionFile($filename, $this);

        //Let's save to cache
        $this->cache[$filename] = ['md5' => $fileMD5] + $reflection->exportSchema();
        $this->memory->saveData(static::MEMORY, $this->cache);

        return $reflection;
    }

    /**
     * Get pre-configured class locator.
     *
     * @param array  $directories
     * @param array  $exclude
     * @param Finder $finder
     * @return ClassLocator
     */
    public function classLocator(
        array $directories = [],
        array $exclude = [],
        Finder $finder = null
    ) {
        $finder = !empty($finder) ?: new Finder();

        if (empty($directories)) {
            $directories = $this->config['directories'];
        }

        if (empty($exclude)) {
            $exclude = $this->config['exclude'];
        }

        //Configuring finder
        return new ClassLocator(
            $this,
            $finder->files()->in($directories)->exclude($exclude)->name('*.php')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function createInjection(\ReflectionClass $class, $context = null)
    {
        return $this->classLocator();
    }
}