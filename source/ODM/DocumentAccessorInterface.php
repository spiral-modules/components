<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\ODM;

use Spiral\Models\AccessorInterface;

/**
 * Declares requirement for every ODM field accessor to be an instance of AccessorInterface and
 * declare it's default value and ability to build array of atomic updated for declared container.
 *
 * Parent model will not be supplied to accessor while schema analysis!
 */
interface DocumentAccessorInterface extends AccessorInterface
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
     * Get generated and manually set document/object atomic updates.
     *
     * @param string $container Name of field or index where object stored under parent.
     *
     * @return array
     */
    public function buildAtomics($container = '');

    /**
     * Accessor default value.
     *
     * @return mixed
     */
    public function defaultValue();
}
