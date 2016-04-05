<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Tokenizer\Prototypes;

use Psr\Log\LoggerAwareInterface;
use Spiral\Core\Component;
use Spiral\Core\Container\InjectableInterface;
use Spiral\Debug\Traits\LoggerTrait;
use Spiral\Tokenizer\Exceptions\LocatorException;
use Spiral\Tokenizer\Tokenizer;
use Spiral\Tokenizer\TokenizerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Base class for Class and Invocation locators.
 */
class AbstractLocator extends Component implements InjectableInterface, LoggerAwareInterface
{
    use LoggerTrait;

    /**
     * Parent injector/factory.
     */
    const INJECTOR = Tokenizer::class;

    /**
     * @invisible
     *
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
     * Available file reflections. Generator.
     *
     * @generate ReflectionFile[]
     */
    protected function availableReflections()
    {
        /**
         * @var SplFileInfo
         */
        foreach ($this->finder->getIterator() as $file) {
            $reflection = $this->tokenizer->fileReflection((string)$file);

            //We are not analyzing files which has includes, it's not safe to require such reflections
            if ($reflection->hasIncludes()) {
                $this->logger()->warning(
                    "File '{filename}' has includes and will be excluded from analysis",
                    ['filename' => (string)$file]
                );

                continue;
            }

            /*
             * @var ReflectionFile $reflection
             */
            yield $reflection;
        }
    }

    /**
     * Safely get class reflection, class loading errors will be blocked and reflection will be
     * excluded from analysis.
     *
     * @param string $class
     *
     * @return \ReflectionClass|null
     */
    protected function classReflection($class)
    {
        $loader = function ($class) {
            throw new LocatorException("Class '{$class}' can not be loaded");
        };

        //To suspend class dependency exception
        spl_autoload_register($loader);

        try {
            return new \ReflectionClass($class);
        } catch (\Exception $exception) {
            $this->logger()->error(
                "Unable to resolve class '{class}', error '{message}'",
                ['class' => $class, 'message' => $exception->getMessage()]
            );

            return;
        } finally {
            spl_autoload_unregister($loader);
        }
    }

    /**
     * Get every class trait (including traits used in parents).
     *
     * @param string $class
     *
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
