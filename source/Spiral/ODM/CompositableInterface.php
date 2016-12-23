<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ODM;

use MongoDB\BSON\Serializable;
use Spiral\Models\AccessorInterface;

/**
 * Declares ability of object to be embedded into RecordEntity or represent set of embedded objects.
 */
interface CompositableInterface extends AccessorInterface, Serializable
{
    /**
     * Composition default state, this value is required in order to properly generate default model
     * state.
     *
     * @return mixed
     */
    public function defaultValue();

    /**
     * Check if composition have any change.
     *
     * @return bool
     */
    public function hasUpdates(): bool;

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

    /**
     * Indicate that composition been properly saved.
     */
    public function flushUpdates();
}