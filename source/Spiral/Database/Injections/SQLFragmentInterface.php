<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Database\Injections;

/**
 * Declares ability to be converted into sql statement.
 */
interface SQLFragmentInterface
{
    /**
     * @return string
     */
    public function sqlStatement();

    /**
     * @return string
     */
    public function __toString();
}