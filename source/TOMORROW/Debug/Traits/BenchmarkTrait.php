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
use Spiral\Debug\BenchmarkerInterface;

trait BenchmarkTrait
{
    /**
     * @return ContainerInterface
     */
    abstract public function container();

    /**
     * Benchmarks used to record duration of long or memory inefficient operations in spiral, you
     * can use profiler panel to view benchmarks later.
     *
     * @param string $record  Benchmark record name.
     * @param string $context Record context (if any).
     * @return bool|float
     */
    protected function benchmark($record = '', $context = '')
    {
        $container = self::getContainer();
        if (empty($container) || !$container->hasBinding(BenchmarkerInterface::class))
        {
            //Nothing to do
            return false;
        }

        return $container->get(BenchmarkerInterface::class)->benchmark($this, $record, $context);
    }
}