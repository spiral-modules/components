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
 * Declares ability to be validated and raise error messages on failure.
 */
interface ValidatesInterface
{
    /**
     * Check if context data is valid.
     *
     * @return bool
     */
    public function isValid(): bool;

    /**
     * Check if context data has errors.
     *
     * @return bool
     */
    public function hasErrors(): bool;

    /**
     * List of errors associated with parent field, every field must have only one error assigned.
     *
     * @param bool $reset Force re-validation
     *
     * @return array
     *
     * @throws ValidationException
     */
    public function getErrors(bool $reset = false): array;
}