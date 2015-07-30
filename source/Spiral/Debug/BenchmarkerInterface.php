<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Debug;

/**
 * Interface responsible for benchmarking.
 */
interface BenchmarkerInterface
{
    /**
     * Benchmarks used to record long or important operations inside spiral components. Method should
     * return elapsed time when record are be closed (same set of arguments has to be provided).
     *
     * @param object $caller  Call initiator (used to de-group events).
     * @param string $record  Benchmark record name.
     * @param string $context Record context (if any).
     * @return bool|float
     */
    public function benchmark($caller, $record, $context = '');
}