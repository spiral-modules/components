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
     * PHP types for phpType() method.
     */
    const INT    = 'int';
    const BOOL   = 'bool';
    const STRING = 'string';
    const FLOAT  = 'float';

    /**
     * Column name.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Internal database type, can vary based on database driver.
     *
     * @return string
     */
    public function getType(): string;

    /**
     * DBMS specific reverse mapping must map database specific type into limited set of abstract
     * types. Value depends on driver implementation.
     *
     * @return string
     */
    public function abstractType(): string;

    /**
     * Must return PHP type column value can be better mapped into: int, bool, string or float.
     *
     * @return string
     */
    public function phpType(): string;

    /**
     * Column size.
     *
     * @return int
     */
    public function getSize(): int;

    /**
     * Column precision.
     *
     * @return int
     */
    public function getPrecision(): int;

    /**
     * Column scale value.
     *
     * @return int
     */
    public function getScale(): int;

    /**
     * Can column store null value?
     *
     * @return bool
     */
    public function isNullable(): bool;

    /**
     * Indication that column has default value.
     *
     * @return bool
     */
    public function hasDefaultValue(): bool;

    /**
     * Get column default value, value must be automatically converted to appropriate internal type.
     *
     * @return mixed
     */
    public function getDefaultValue();
}
