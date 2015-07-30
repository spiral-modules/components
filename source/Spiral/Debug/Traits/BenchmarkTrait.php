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

/**
 * Provides access to benchmark function.
 */
trait BenchmarkTrait
{
    /**
     * @var BenchmarkerInterface
     */
    private $benchmarker = null;

    /**
     * @return ContainerInterface
     */
    abstract public function container();

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
     * Benchmarks used to record long or important operations inside spiral components. Method should
     * return elapsed time when record are be closed (same set of arguments has to be provided).
     *
     * @param string $record  Benchmark record name.
     * @param string $context Record context (if any).
     * @return bool|float
     */
    public function benchmark($record, $context = '')
    {
        if (empty($this->benchmarker))
        {
            if (empty($container = $this->container()) || !$container->hasBinding(BenchmarkerInterface::class))
            {
                //Nothing to do
                return false;
            }

            $this->benchmarker = $container->get(BenchmarkerInterface::class);
        }

        return $this->benchmarker->benchmark($this, $record, $context);
    }
}