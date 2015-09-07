<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Models;

use Spiral\Models\Exceptions\EntityExceptionInterface;
use Spiral\Validation\ValidatesInterface;

/**
 * Generic data entity instance.
 */
interface EntityInterface extends ValidatesInterface
{
    /**
     * Check if entity has field by it's name.
     *
     * @param string $name
     * @return bool
     */
    public function hasField($name);

    /**
     * Set entity field value.
     *
     * @param string $name
     * @param mixed  $value
     * @throws EntityExceptionInterface
     */
    public function setField($name, $value);

    /**
     * Get value of entity field.
     *
     * @param string $name
     * @param mixed  $default
     * @return mixed|AccessorInterface
     * @throws EntityExceptionInterface
     */
    public function getField($name, $default = null);

    /**
     * Update entity fields using mass assignment. Only allowed fields must be set.
     *
     * @param array|\Traversable $fields
     * @throws EntityExceptionInterface
     */
    public function setFields($fields = []);

    /**
     * Get entity field values.
     *
     * @return array
     * @throws EntityExceptionInterface
     */
    public function getFields();
}