<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Validation\Checkers;

use Spiral\Core\Container\SingletonInterface;
use Spiral\Validation\Checker;

/**
 * Variable type checks.
 */
class TypeChecker extends Checker implements SingletonInterface
{
    /**
     * Declaring to IoC to construct class only once.
     */
    const SINGLETON = self::class;

    /**
     * {@inheritdoc}
     */
    protected $messages = [
        "notEmpty" => "[[This field is required.]]",
        "boolean"  => "[[Not valid boolean.]]",
        "datetime" => "[[Not valid datetime.]]",
        "timezone" => "[[Not valid timezone.]]"
    ];

    /**
     * Value should not be empty.
     *
     * @param mixed $value
     * @param bool  $trim
     * @return bool
     */
    public function notEmpty($value, $trim = true)
    {
        if ($trim && is_string($value) && strlen(trim($value)) == 0) {
            return false;
        }

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