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
 * Scalar number validations.
 */
class NumberChecker extends Checker implements SingletonInterface
{
    /**
     * {@inheritdoc}
     */
    protected $messages = [
        'range'  => '[[Your value should be in range of {0}-{1}.]]',
        'higher' => '[[Your value should be higher than {0}.]]',
        'lower'  => '[[Your value should be lower than {0}.]]',
    ];

    /**
     * Check if number in specified range.
     *
     * @param float $value
     * @param float $begin
     * @param float $end
     *
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
     *
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
     *
     * @return bool
     */
    public function lower($value, $limit)
    {
        return $value <= $limit;
    }
}
