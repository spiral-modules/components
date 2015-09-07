<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Validation;

/**
 * Declares ability to be validated and raise error messages on failure.
 */
interface ValidatesInterface
{
    /**
     * Attach custom validator to model.
     *
     * @param ValidatorInterface $validator
     */
    public function setValidator(ValidatorInterface $validator);

    /**
     * Check if context data is valid.
     *
     * @return bool
     */
    public function isValid();

    /**
     * Check if context data has errors.
     *
     * @return bool
     */
    public function hasErrors();

    /**
     * List of errors associated with parent field, every field must have only one error assigned.
     *
     * @param bool $reset Force re-validation.
     * @return array
     */
    public function getErrors($reset = false);
}