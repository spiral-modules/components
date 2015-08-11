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
 * Declares that accessor has it's own update operations.
 */
interface EmbeddableInterface extends AccessorInterface
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
}