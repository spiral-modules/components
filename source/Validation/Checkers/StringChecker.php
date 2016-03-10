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
 * String validations.
 */
class StringChecker extends Checker implements SingletonInterface
{
    /**
     * {@inheritdoc}
     */
    protected $messages = [
        'regexp'  => '[[Your value does not match required pattern.]]',
        'shorter' => '[[Enter text shorter or equal to {0}.]]',
        'longer'  => '[[Your text must be longer or equal to {0}.]]',
        'length'  => '[[Your text length must be exactly equal to {0}.]]',
        'range'   => '[[Text length should be in range of {0}-{1}.]]',
    ];

    /**
     * Check string using regexp.
     *
     * @param string $string
     * @param string $expression
     *
     * @return bool
     */
    public function regexp($string, $expression)
    {
        return is_string($string) && preg_match($expression, $string);
    }

    /**
     * Check if string length is shorter or equal that specified value.
     *
     * @param string $string
     * @param int    $length
     *
     * @return bool
     */
    public function shorter($string, $length)
    {
        return mb_strlen($string) <= $length;
    }

    /**
     * Check if string length is longer or equal that specified value.
     *
     * @param string $string
     * @param int    $length
     *
     * @return bool
     */
    public function longer($string, $length)
    {
        return mb_strlen($string) >= $length;
    }

    /**
     * Check if string length are equal to specified value.
     *
     * @param string $string
     * @param int    $length
     *
     * @return bool
     */
    public function length($string, $length)
    {
        return mb_strlen($string) == $length;
    }

    /**
     * Check if string length are fits in specified range.
     *
     * @param string $string
     * @param int    $lengthA
     * @param int    $lengthB
     *
     * @return bool
     */
    public function range($string, $lengthA, $lengthB)
    {
        return (mb_strlen($string) >= $lengthA) && (mb_strlen($string) <= $lengthB);
    }
}
