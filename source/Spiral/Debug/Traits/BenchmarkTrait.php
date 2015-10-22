<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Debug\Traits;

use Spiral\Core\ContainerInterface;
use Spiral\Debug\BenchmarkerInterface;

/**
 * Provides access to benchmark function.
 */
trait BenchmarkTrait
{
    /**
     * @invisible
     * @var BenchmarkerInterface
     */
    private $benchmarker = null;

    /**
     * Set custom benchmarker.
     *
     * @param BenchmarkerInterface $benchmarker
     */
    public function setBenchmarker(BenchmarkerInterface $benchmarker)
    {
        $this->benchmarker = $benchmarker;
    }

    /**
     * Benchmarks used to record long or important operations inside spiral components. Method
     * should return elapsed time when record are be closed (same set of arguments has to be
     * provided).
     *
     * @param string $record  Benchmark record name.
     * @param string $context Record context (if any).
     * @return bool|float|mixed
     */
    protected function benchmark($record, $context = '')
    {
        if (empty($this->benchmarker)) {

            if (
                empty($container = $this->container())
                || !$container->has(BenchmarkerInterface::class)
            ) {
                //Nothing to do
                return false;
            }

            $this->benchmarker = $container->get(BenchmarkerInterface::class);
        }

        return $this->benchmarker->benchmark($this, $record, $context);
    }

    /**
     * @return ContainerInterface
     */
    abstract protected function container();
}