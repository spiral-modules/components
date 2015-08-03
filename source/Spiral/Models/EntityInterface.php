<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Models;

use Spiral\Models\Exceptions\EntityException;

/**
 * Generic data entity instance.
 */
interface EntityInterface extends \ArrayAccess
{
    /**
     * Set model field value.
     *
     * @param string $name
     * @param mixed  $value
     * @throws EntityException
     */
    public function setField($name, $value);

    /**
     * Get value of model field.
     *
     * @param string $name
     * @param mixed  $default
     * @return mixed|AccessorInterface
     * @throws EntityException
     */
    public function getField($name, $default = null);

    /**
     * Update model fields using mass assignment. Only known fields must be set.
     *
     * @param array|\Traversable $fields
     * @throws EntityException
     */
    public function setFields($fields = []);

    /**
     * Get entity fields.
     *
     * @return array
     * @throws EntityException
     */
    public function getFields();
}