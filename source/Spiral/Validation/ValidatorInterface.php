<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Validation;

use Spiral\Validation\Exceptions\ValidationException;

/**
 * Validators responsible for data validations. Validation rules are implementation dependent but
 * should always be specified in array form (same as in default implementation :)).
 */
interface ValidatorInterface
{
    /**
     * @param array|\ArrayAccess $data  Data to be validated.
     * @param array              $rules Validation rules.
     */
    public function __construct($data, array $rules);

    /**
     * Update validation data (context).
     *
     * @param array|\ArrayAccess $data
     * @return self
     * @throws ValidationException
     */
    public function setData($data);

    /**
     * Update validation rules.
     *
     * @param array $validates
     * @return self
     */
    public function setRules(array $validates);

    /**
     * Check if context data valid accordingly to provided rules.
     *
     * @return bool
     * @throws ValidationException
     */
    public function isValid();

    /**
     * Evil tween of isValid() method should return true if context data is not valid.
     *
     * @return bool
     * @throws ValidationException
     */
    public function hasErrors();

    /**
     * Attach error to data field.
     *
     * @param string $field
     * @param string $message
     */
    public function setError($field, $message);

    /**
     * List of errors associated with parent field, every field should have only one error assigned.
     *
     * @return array
     */
    public function getErrors();
}