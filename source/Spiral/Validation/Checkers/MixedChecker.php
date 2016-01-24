<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Validation\Checkers;

use Spiral\Core\Container\SingletonInterface;
use Spiral\Validation\Checker;

/**
 * Validations can't be fitted to any other checker.
 */
class MixedChecker extends Checker implements SingletonInterface
{
    /**
     * {@inheritdoc}
     */
    protected $messages = [
        "cardNumber" => "[[Please enter valid card number.]]"
    ];

    /**
     * Check credit card passed by Luhn algorithm.
     *
     * @link http://en.wikipedia.org/wiki/Luhn_algorithm
     * @param string $cardNumber
     * @return bool
     */
    public function cardNumber($cardNumber)
    {
        if (!is_string($cardNumber) || strlen($cardNumber) < 12) {
            return false;
        }

        $result = 0;
        $odd = strlen($cardNumber) % 2;
        preg_replace('/[^0-9]+/', '', $cardNumber);

        for ($i = 0; $i < strlen($cardNumber); $i++) {
            $result += $odd
                ? $cardNumber[$i]
                : (($cardNumber[$i] * 2 > 9) ? $cardNumber[$i] * 2 - 9 : $cardNumber[$i] * 2);

            $odd = !$odd;
        }

        // Check validity.
        return ($result % 10 == 0) ? true : false;
    }

    /**
     * Check if value matches value from another field.
     *
     * @param string $value
     * @param string $field
     * @param bool   $strict
     * @return bool
     */
    public function match($value, $field, $strict = false)
    {
        if ($strict) {
            return $value === $this->validator()->field($field, null);
        }

        return $value == $this->validator()->field($field, null);
    }
}