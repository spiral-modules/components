<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Schemas;

/**
 * Represents table schema column abstraction.
 */
interface ColumnInterface
{
    /**
     * Column name.
     *
     * @return string
     */
    public function getName();

    /**
     * Internal database type, can vary based on database driver.
     *
     * @return string
     */
    public function getType();

    /**
     * Must return PHP type column value can be better mapped into: int, bool, string or float.
     *
     * @return string
     */
    public function phpType();

    /**
     * Column size.
     *
     * @return int
     */
    public function getSize();

    /**
     * Column precision.
     *
     * @return int
     */
    public function getPrecision();

    /**
     * Column scale value.
     *
     * @return int
     */
    public function getScale();

    /**
     * Can column store null value?
     *
     * @return bool
     */
    public function isNullable();

    /**
     * Indication that column has default value.
     *
     * @return bool
     */
    public function hasDefaultValue();

    /**
     * Get column default value, value must be automatically converted to appropriate internal type.
     *
     * @return mixed
     */
    public function getDefaultValue();
}
