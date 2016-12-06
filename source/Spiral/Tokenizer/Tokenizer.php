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
use Spiral\Core\Exceptions\Container\InjectionException;
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
    use LoggerTrait, BenchmarkTrait, TokensTrait;

    /**
     * Memory section.
     */
    const MEMORY_LOCATION = 'tokenizer';

    /**
     * @var TokenizerConfig
     */
    protected $config;

    /**
     * @invisible
     *
     * @var FilesInterface
     */
    protected $files;

    /**
     * @invisible
     *
     * @var HippocampusInterface
     */
    protected $memory;

    /**
     * Tokenizer constructor.
     *
     * @param FilesInterface       $files
     * @param TokenizerConfig      $config
     * @param HippocampusInterface $memory
     */
    public function __construct(
        FilesInterface $files,
        TokenizerConfig $config,
        HippocampusInterface $memory
    ) {
        $this->files = $files;
        $this->config = $config;
        $this->memory = $memory;
    }

    /**
     * {@inheritdoc}
     */
    public function fileReflection(string $filename): ReflectionFile
    {
        $fileMD5 = $this->files->md5($filename = $this->files->normalizePath($filename));

        $reflection = new ReflectionFile(
            $this->normalizeTokens(token_get_all($this->files->read($filename))),
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
     *
     * @return ClassLocatorInterface
     */
    public function classLocator(
        array $directories = [],
        array $exclude = [],
        Finder $finder = null
    ): ClassLocatorInterface {
        return new ClassLocator($this, $this->prepareFinder($finder, $directories, $exclude));
    }

    /**
     * Get pre-configured invocation locator.
     *
     * @param array  $directories
     * @param array  $exclude
     * @param Finder $finder
     *
     * @return InvocationLocatorInterface
     */
    public function invocationLocator(
        array $directories = [],
        array $exclude = [],
        Finder $finder = null
    ): InvocationLocatorInterface {
        return new InvocationLocator($this, $this->prepareFinder($finder, $directories, $exclude));
    }

    /**
     * {@inheritdoc}
     *
     * @throws InjectionException
     */
    public function createInjection(\ReflectionClass $class, $context = null)
    {
        if ($class->isSubclassOf(ClassLocatorInterface::class)) {
            return $this->classLocator();
        } elseif ($class->isSubclassOf(InvocationLocatorInterface::class)) {
            return $this->invocationLocator();
        }

        throw new InjectionException("Unable to create injection for {$class}");
    }

    /**
     * @param Finder|null $finder
     * @param array       $directories
     * @param array       $exclude
     * @return Finder
     */
    private function prepareFinder(
        Finder $finder = null,
        array $directories = [],
        array $exclude = []
    ): Finder {
        $finder = $finder ?? new Finder();

        if (empty($directories)) {
            $directories = $this->config->getDirectories();
        }

        if (empty($exclude)) {
            $exclude = $this->config->getExcludes();
        }

        return $finder->files()->in($directories)->exclude($exclude)->name('*.php');
    }
}
