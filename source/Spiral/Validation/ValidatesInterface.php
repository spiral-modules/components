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
     * @param bool $reset Clean errors after receiving every message.
     * @return array
     */
    public function getErrors($reset = false);
}