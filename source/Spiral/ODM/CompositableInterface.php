<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ODM;

use Spiral\Models\AccessorInterface;

/**
 * Declares ability of object to be embedded into RecordEntity or represent set of embedded objects.
 */
interface CompositableInterface extends AccessorInterface
{
    /**
     * Check if composition have any change.
     *
     * @return bool
     */
    public function hasChanges(): bool;

    /**
     * Indicate that composition been properly saved.
     */
    public function flushChanges();

    /**
     * Get generated and manually set document/object atomic updates.
     *
     * Example: $set: {'key': 'value'}
     *
     * @param string $container Name of field or index where object stored under parent.
     *
     * @return array
     */
    public function buildAtomics(string $container = ''): array;
}