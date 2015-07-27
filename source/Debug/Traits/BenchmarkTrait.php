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
     * Global container access is required in some cases.
     *
     * @return ContainerInterface
     */
    abstract public function getContainer();

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
        if (empty($this->getContainer()))
        {
            //Nothing to do
            return false;
        }

        return $this->getContainer()->get(BenchmarkerInterface::class)->benchmark(
            $this,
            $record,
            $context
        );
    }
}