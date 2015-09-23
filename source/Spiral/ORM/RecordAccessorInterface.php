<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM;

use Spiral\Database\Entities\Driver;
use Spiral\Database\Injections\SQLFragmentInterface;
use Spiral\Models\AccessorInterface;

/**
 * Declares requirement for every ORM field accessor to declare it's driver depended value and
 * control it's updates.
 *
 * ORM accessors are much more simple by initiation that ODM accessors.
 */
interface RecordAccessorInterface extends AccessorInterface
{
    /**
     * Check if object has any update.
     *
     * @return bool
     */
    public function hasUpdates();

    /**
     * Mark object as successfully updated and flush all existed atomic operations and updates.
     */
    public function flushUpdates();

    /**
     * Create update value or statement to be used in DBAL update builder. May return SQLFragments
     * and expressions.
     *
     * @param string $field Name of field where accessor associated to.
     * @return mixed|SQLFragmentInterface
     */
    public function compileUpdates($field = '');

    /**
     * Accessor default value (must be specific to driver).
     *
     * @param Driver $driver
     * @return mixed
     */
    public function defaultValue(Driver $driver);
}