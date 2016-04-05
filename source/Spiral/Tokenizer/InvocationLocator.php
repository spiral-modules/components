<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Tokenizer;

use Spiral\Tokenizer\Prototypes\AbstractLocator;
use Spiral\Tokenizer\Reflections\ReflectionInvocation;

/**
 * Can locate invocations in a specified directory. Can only find simple invocations!
 *
 * @todo use ast
 */
class InvocationLocator extends AbstractLocator implements InvocationLocatorInterface
{
    /**
     * {@inheritdoc}
     */
    public function getInvocations(\ReflectionFunctionAbstract $function)
    {
        $result = [];
        foreach ($this->availableInvocations($function->getName()) as $invocation) {
            if ($this->isTargeted($invocation, $function)) {
                $result[] = $invocation;
            }
        }

        return $result;
    }

    /**
     * Invocations available in finder scope.
     *
     * @param string $signature Method or function signature (name), for pre-filtering.
     * @return ReflectionInvocation[]
     */
    protected function availableInvocations($signature = '')
    {
        $invocations = [];

        $signature = strtolower(trim($signature, '\\'));
        foreach ($this->availableReflections() as $reflection) {
            foreach ($reflection->getInvocations() as $invocation) {
                if (
                    !empty($signature)
                    && strtolower(trim($invocation->getName(), '\\')) != $signature
                ) {
                    continue;
                }

                $invocations[] = $invocation;
            }
        }

        return $invocations;
    }

    /**
     * @param ReflectionInvocation        $invocation
     * @param \ReflectionFunctionAbstract $function
     * @return bool
     */
    protected function isTargeted(
        ReflectionInvocation $invocation,
        \ReflectionFunctionAbstract $function
    ) {
        if ($function instanceof \ReflectionFunction) {
            return !$invocation->isMethod();
        }

        if (empty($class = $this->classReflection($invocation->getClass()))) {
            //Unable to get reflection
            return false;
        }

        /**
         * @var \ReflectionMethod $function
         */
        $target = $function->getDeclaringClass();

        if ($target->isTrait()) {
            //Let's compare traits
            return in_array($target->getName(), $this->getTraits($invocation->getClass()));
        }

        return $class->getName() == $target->getName() || $class->isSubclassOf($target);
    }
}
