<?php
/**
 * components
 *
 * @author    Wolfy-J
 */

namespace Spiral\ORM;

/**
 * Base definition for cross RecordInterface relations.
 */
interface RelationInterface
{
    /**
     * Indicates that relation commands must be executed prior to parent command.
     *
     * @return bool
     */
    public function isLeading(): bool;

    /**
     * Create version of relation with given parent and loaded data (if any).
     *
     * @param RecordInterface $parent
     * @param bool            $loaded
     * @param array|null      $data
     *
     * @return RelationInterface
     */
    public function withContext(
        RecordInterface $parent,
        bool $loaded = false,
        array $data = null
    ): RelationInterface;

    /**
     * Get class relation points to. Usually for debug purposes.
     *
     * @return string
     */
    public function getClass(): string;

    /**
     * Return true if relation has any loaded data (method withContext) must be called prior to
     * that.
     *
     * @return bool
     */
    public function isLoaded(): bool;
}