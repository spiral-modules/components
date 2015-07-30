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
 * Scalar number validations.
 */
class NumberChecker extends Checker
{
    /**
     * {@inheritdoc}
     */
    protected $messages = [
        "range"  => "[[Field '{field}' should be in range of {0}-{1}.]]",
        "higher" => "[[Field '{field}' should be higher than {0}.]]",
        "lower"  => "[[Field '{field}' should be lower than {0}.]]"
    ];

    /**
     * Check if number in specified range.
     *
     * @param float $value
     * @param float $begin
     * @param float $end
     * @return bool
     */
    public function range($value, $begin, $end)
    {
        return $value >= $begin && $value <= $end;
    }

    /**
     * Check if value is bigger or equal that specified.
     *
     * @param float $value
     * @param float $limit
     * @return bool
     */
    public function higher($value, $limit)
    {
        return $value >= $limit;
    }

    /**
     * Check if value smaller of equal that specified.
     *
     * @param float $value
     * @param float $limit
     * @return bool
     */
    public function lower($value, $limit)
    {
        return $value <= $limit;
    }
}