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
 * String validations.
 */
class StringChecker extends Checker
{
    /**
     * {@inheritdoc}
     */
    protected $messages = [
        "regexp"  => "[[Field '{field}' does not match required pattern.]]",
        "shorter" => "[[Field length '{field}' should be shorter or equal to {0}.]]",
        "longer"  => "[[Field length '{field}' should be longer or equal to {0}.]]",
        "exactly" => "[[Field length '{field}' should be exactly equal to {0}.]]",
        "range"   => "[[Field length '{field}' should be in range of {0}-{1}.]]"
    ];

    /**
     * Check string using regexp.
     *
     * @param string $string
     * @param string $expression
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
     * @return bool
     */
    public function exactly($string, $length)
    {
        return mb_strlen($string) == $length;
    }

    /**
     * Check if string length are fits in specified range.
     *
     * @param string $string
     * @param int    $lengthA
     * @param int    $lengthB
     * @return bool
     */
    public function range($string, $lengthA, $lengthB)
    {
        return (mb_strlen($string) >= $lengthA) && (mb_strlen($string) <= $lengthB);
    }
}