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
 * Variable type checks.
 */
class TypeChecker extends Checker
{
    /**
     * {@inheritdoc}
     */
    protected $messages = [
        "notEmpty" => "[[Field '{field}' should not be empty.]]",
        "boolean"  => "[[Field '{field}' is not valid boolean.]]",
        "datetime" => "[[Field '{field}' is not valid datetime.]]",
        "timezone" => "[[Field '{field}' is not valid timezone.]]"
    ];

    /**
     * Value should not be empty.
     *
     * @param mixed $value
     * @return bool
     */
    public function notEmpty($value)
    {
        return !empty($value);
    }

    /**
     * Value has to be boolean or integer[0,1].
     *
     * @param mixed $value
     * @return bool
     */
    public function boolean($value)
    {
        return is_bool($value) || (is_numeric($value) && ($value === 0 || $value === 1));
    }

    /**
     * Value has to be valid datetime definition including numeric timestamp.
     *
     * @param mixed $value
     * @return bool
     */
    public function datetime($value)
    {
        if (!is_scalar($value)) {
            return false;
        }

        if (is_numeric($value)) {
            return true;
        }

        return (int)strtotime($value) != 0;
    }

    /**
     * Value has to be valid timezone.
     *
     * @param mixed $value
     * @return bool
     */
    public function timezone($value)
    {
        return in_array($value, \DateTimeZone::listIdentifiers());
    }
}