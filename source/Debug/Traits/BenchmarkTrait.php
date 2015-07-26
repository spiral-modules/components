<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Debug\Traits;

trait BenchmarkTrait
{

    /**
     * Global container access is required in some cases.
     *
     * @return ContainerInterface
     */
    abstract public function getContainer();


    protected function benchmark()
    {
        if (empty(self::getContainer()))
        {
            return null;
        }

        echo "BENCHMARK";
    }
}