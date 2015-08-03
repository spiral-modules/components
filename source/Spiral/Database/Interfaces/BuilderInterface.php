<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Interfaces;

use Spiral\Database\Exceptions\BuilderException;
use Spiral\Database\Exceptions\QueryException;
use Spiral\Database\Interfaces\Injections\SQLFragmentInterface;

/**
 * Declares generic query builder functionality.
 */
interface BuilderInterface extends SQLFragmentInterface
{
    /**
     * Get ordered list of builder parameters.
     *
     * @return array
     * @throws BuilderException
     */
    public function getParameters();

    /**
     * Run built statement against parent database.
     *
     * @throws QueryException
     */
    public function run();
}