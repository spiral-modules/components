<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Schemas;

/**
 * Represents single table index associated with set of columns.
 */
interface IndexInterface
{
    /**
     * Index name.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Check if index is unique.
     *
     * @return bool
     */
    public function isUnique(): bool;

    /**
     * Column names used to form index.
     *
     * @return array
     */
    public function getColumns(): array;
}
