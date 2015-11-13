<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Tokenizer;

use Psr\Log\LoggerAwareInterface;
use Spiral\Core\Component;
use Spiral\Core\Container\InjectableInterface;
use Spiral\Core\Exceptions\LoaderException;
use Spiral\Core\Traits\InjectableTrait;
use Spiral\Debug\Traits\LoggerTrait;
use Spiral\Tokenizer\Exceptions\LocatorException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Can locate classes in a specified directory.
 */
class ClassLocator extends Component implements
    LocatorInterface,
    InjectableInterface,
    LoggerAwareInterface
{
    /**
     * Injection over constant.
     */
    use InjectableTrait, LoggerTrait;

    /**
     * Parent injector/factory.
     */
    const INJECTOR = Tokenizer::class;

    /**
     * @invisible
     * @var TokenizerInterface
     */
    protected $tokenizer = null;

    /**
     * @var Finder
     */
    protected $finder = null;

    /**
     * @param TokenizerInterface $tokenizer
     * @param Finder             $finder
     */
    public function __construct(TokenizerInterface $tokenizer, Finder $finder)
    {
        $this->tokenizer = $tokenizer;
        $this->finder = $finder;
    }

    /**
     * {!@inheritdoc}
     */
    public function getClasses($target = null)
    {
        if (!empty($target) && (is_object($target) || is_string($target))) {
            $target = new \ReflectionClass($target);
        }

        $result = [];
        foreach ($this->availableClasses() as $class) {
            if (empty($reflection = $this->classReflection($class))) {
                //Unable to get reflection
                continue;
            }

            if (!$this->isTargeted($reflection, $target) || $reflection->isInterface()) {
                continue;
            }

            $result[$reflection->getName()] = [
                'name'     => $reflection->getName(),
                'filename' => $reflection->getFileName(),
                'abstract' => $reflection->isAbstract()
            ];
        }

        return $result;
    }

    /**
     * Classes available in finder scope.
     *
     * @return array
     */
    protected function availableClasses()
    {
        $classes = [];

        /**
         * @var SplFileInfo $file
         */
        foreach ($this->finder->getIterator() as $file) {
            $reflection = $this->tokenizer->fileReflection((string)$file);

            //We are not analyzing files which has includes, it's not safe to require such classes
            if ($reflection->hasIncludes()) {
                $this->logger()->warning(
                    "File '{filename}' has includes and will be excluded from analysis.",
                    ['filename' => (string)$file]
                );

                continue;
            }

            $classes = array_merge($classes, $reflection->getClasses());
        }

        return $classes;
    }

    /**
     * Check if given class targeted by locator.
     *
     * @param \ReflectionClass      $class
     * @param \ReflectionClass|null $target
     * @return bool
     */
    protected function isTargeted(\ReflectionClass $class, \ReflectionClass $target = null)
    {
        if (empty($target)) {
            return true;
        }

        if (!$target->isTrait()) {
            //Target is interface or class
            return $class->isSubclassOf($target) || $class->getName() == $target->getName();
        }

        //Checking using traits
        return !in_array($target->getName(), $this->getTraits($class->getName()));
    }

    /**
     * Safely get class reflection, if any error occured while class including no reflection will
     * be returned.
     *
     * @param string $class
     * @return \ReflectionClass|null
     */
    protected function classReflection($class)
    {
        $loader = function ($class) {
            throw new LocatorException("Class '{$class}' can not be loaded.");
        };

        //To suspend class dependency exception
        spl_autoload_register($loader);

        try {
            return new \ReflectionClass($class);
        } catch (\Exception $exception) {
            $this->logger()->error(
                "Unable to resolve class '{class}', error '{message}'.",
                ['class' => $class, 'message' => $exception->getMessage()]
            );

            return null;
        } finally {
            spl_autoload_unregister($loader);
        }
    }

    /**
     * Get every class trait (including traits used in parents).
     *
     * @param string $class
     * @return array
     */
    protected function getTraits($class)
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
}