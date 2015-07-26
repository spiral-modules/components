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
            //Nothing to do
            return null;
        }
    }
}