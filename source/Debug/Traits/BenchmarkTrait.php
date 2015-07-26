<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Debug\Traits;

use Spiral\Core\ContainerInterface;
use Spiral\Debug\Debugger;

trait BenchmarkTrait
{
    /**
     * Global container access is required in some cases.
     *
     * @return ContainerInterface
     */
    abstract public function getContainer();

    /**
     * Benchmarks used to record duration of long or memory inefficient operations in spiral, you
     * can use profiler panel to view benchmarks later.
     *
     * Every additional record name will be joined with caller name.
     *
     * @param string|array $record Record name(s).
     * @return bool|float
     */
    protected function benchmark($record = '')
    {
        if (empty(self::getContainer()))
        {
            //Nothing to do
            return null;
        }

        return Debugger::getInstance(
            self::getContainer()
        )->benchmark(static::class, func_get_args());
    }
}