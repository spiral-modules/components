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
     * @param string             $method
     * @param mixed              $value
     * @param array              $arguments
     * @param ValidatorInterface $validator Parent validator.
     *
     * @return bool|CheckerInterface Return self to indicate that error happen (used to properly
     *                               resolve message). @todo optimize
     *
     * @throws CheckerException
     */
    public function check(
        string $method,
        $value,
        array $arguments = [],
        ValidatorInterface $validator = null
    );

    /**
     * Return default error message for checker condition.
     *
     * @param string $method
     *
     * @return string
     *
     * @throws CheckerException
     */
    public function getMessage(string $method): string;
}