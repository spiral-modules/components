<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Validation;

use Spiral\Validation\Exceptions\CheckerException;

/**
 * Interface CheckerInterface
 *
 * @package Spiral\Validation
 */
interface CheckerInterface
{
    /**
     * Check value using checker method.
     *
     * @param string    $method
     * @param mixed     $value
     * @param array     $arguments
     * @param Validator $validator Parent validator.
     *
     * @return mixed
     *
     * @throws CheckerException
     */
    public function check($method, $value, array $arguments = [], Validator $validator = null);

    /**
     * Return default error message for checker condition.
     *
     * @param string $method
     *
     * @return string
     *
     * @throws CheckerException
     */
    public function getMessage($method);
}