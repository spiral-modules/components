<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Validation\Checkers;

use Spiral\Validation\Checker;

/**
 * Validations can't be fitted to any other checker.
 */
class MixedChecker extends Checker
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
}