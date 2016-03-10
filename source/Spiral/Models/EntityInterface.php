<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Models;

use Spiral\Models\Exceptions\ExceptionInterface;
use Spiral\Validation\ValidatesInterface;

/**
 * Generic data entity instance.
 */
interface EntityInterface extends ValidatesInterface
{
    /**
     * Check if field known to entity, field value can be null!
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasField($name);

    /**
     * Set entity field value.
     *
     * @param string $name
     * @param mixed  $value
     *
     * @throws ExceptionInterface
     */
    public function setField($name, $value);

    /**
     * Get value of entity field.
     *
     * @param string $name
     * @param mixed  $default
     *
     * @return mixed|AccessorInterface
     *
     * @throws ExceptionInterface
     */
    public function getField($name, $default = null);

    /**
     * Update entity fields using mass assignment. Only allowed fields must be set.
     *
     * @param array|\Traversable $fields
     *
     * @throws ExceptionInterface
     */
    public function setFields($fields = []);

    /**
     * Get entity field values.
     *
     * @return array
     *
     * @throws ExceptionInterface
     */
    public function getFields();
}
