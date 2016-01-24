<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Tokenizer;

use Spiral\Core\Component;
use Spiral\Core\Container\InjectorInterface;
use Spiral\Core\Container\SingletonInterface;
use Spiral\Core\HippocampusInterface;
use Spiral\Debug\Traits\BenchmarkTrait;
use Spiral\Debug\Traits\LoggerTrait;
use Spiral\Files\FilesInterface;
use Spiral\Tokenizer\Configs\TokenizerConfig;
use Spiral\Tokenizer\Reflections\ReflectionFile;
use Spiral\Tokenizer\Traits\TokensTrait;
use Symfony\Component\Finder\Finder;

/**
 * Default implementation of spiral tokenizer support while and blacklisted directories and etc.
 */
class Tokenizer extends Component implements SingletonInterface, TokenizerInterface, InjectorInterface
{
    /**
     * Required traits.
     */
    use LoggerTrait, BenchmarkTrait, TokensTrait;

    /**
     * Memory section.
     */
    const MEMORY_LOCATION = 'tokenizer';

    /**
     * @var TokenizerConfig
     */
    protected $config = null;

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
     * @param FilesInterface       $files
     * @param TokenizerConfig      $config
     * @param HippocampusInterface $runtime
     */
    public function __construct(
        FilesInterface $files,
        TokenizerConfig $config,
        HippocampusInterface $runtime
    ) {
        $this->files = $files;
        $this->config = $config;
        $this->memory = $runtime;
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated this method creates looped dependencies, drop it
     */
    public function fetchTokens($filename)
    {
        return $this->normalizeTokens(token_get_all($this->files->read($filename)));
    }

    /**
     * {@inheritdoc}
     */
    public function fileReflection($filename)
    {
        $fileMD5 = $this->files->md5($filename = $this->files->normalizePath($filename));

        $reflection = new ReflectionFile(
            $this->fetchTokens($filename),
            (array)$this->memory->loadData($fileMD5, self::MEMORY_LOCATION)
        );

        //Let's save to cache
        $this->memory->saveData($fileMD5, $reflection->exportSchema(), static::MEMORY_LOCATION);

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
            $directories = $this->config->getDirectories();
        }

        if (empty($exclude)) {
            $exclude = $this->config->getExcludes();
        }

        //Configuring finder
        return new ClassLocator(
            $this,
            $finder->files()->in($directories)->exclude($exclude)->name('*.php')
        );
    }

    /**
     * Get pre-configured invocation locator.
     *
     * @param array  $directories
     * @param array  $exclude
     * @param Finder $finder
     * @return ClassLocator
     */
    public function invocationLocator(
        array $directories = [],
        array $exclude = [],
        Finder $finder = null
    ) {
        $finder = !empty($finder) ?: new Finder();

        if (empty($directories)) {
            $directories = $this->config->getDirectories();
        }

        if (empty($exclude)) {
            $exclude = $this->config->getExcludes();
        }

        //Configuring finder
        return new InvocationLocator(
            $this,
            $finder->files()->in($directories)->exclude($exclude)->name('*.php')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function createInjection(\ReflectionClass $class, $context = null)
    {
        if ($class->isSubclassOf(ClassLocatorInterface::class)) {
            return $this->classLocator();
        } else {
            return $this->invocationLocator();
        }
    }
}