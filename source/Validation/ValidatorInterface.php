<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Validation;

use Spiral\Validation\Exceptions\ValidationException;

/**
 * Validators responsible for data validations. Validation rules are implementation dependent but
 * should always be specified in array form relative to validator implementation.
 */
interface ValidatorInterface
{
    /**
     * @param array              $rules Validation rules.
     * @param array|\ArrayAccess $data  Data to be validated.
     */
    //public function __construct(array $rules = [], $data = []);

    /**
     * Update validation rules.
     *
     * @param array $rules
     *
     * @return self
     */
    public function setRules(array $rules);

    /**
     * Update validation data (context).
     *
     * @param array|\ArrayAccess $data
     *
     * @return self
     *
     * @throws ValidationException
     */
    public function setData($data);

    /**
     * Register outer validation error.
     *
     * @param string $field
     * @param string $error
     *
     * @return self
     */
    public function registerError($field, $error);

    /**
     * Flush all registered errors.
     *
     * @return self
     */
    public function flushRegistered();

    /**
     * Check if context data valid accordingly to provided rules.
     *
     * @return bool
     *
     * @throws ValidationException
     */
    public function isValid();

    /**
     * Evil tween of isValid() method should return true if context data is not valid.
     *
     * @return bool
     *
     * @throws ValidationException
     */
    public function hasErrors();

    /**
     * List of errors associated with parent field, every field should have only one error assigned.
     *
     * @return array
     */
    public function getErrors();
}
