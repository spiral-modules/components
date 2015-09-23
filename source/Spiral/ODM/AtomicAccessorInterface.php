<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ODM;

use Spiral\Models\AccessorInterface;

/**
 * Declares requirement for every ODM field accessor to be an instance of AccessorInterface and
 * declare it's default value. In addition constructor is unified and no container used to create
 * accessors, however accessor still can resolve it's dependencies using container() method of
 * ODM component which must always be provided by parent document.
 *
 * Parent model will not be supplied to accessor while schema analysis!
 */
interface AtomicAccessorInterface extends AccessorInterface
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