<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Interfaces\Entities\Schemas;

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


    public function hasDefaultValue();

    /**
     * Get column default value, value will be automatically converted to appropriate internal type.
     *
     * @return mixed
     */
    public function getDefaultValue();
}