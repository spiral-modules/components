<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral;

/**
 * Format string using previously named arguments from values array. Arguments that are not found
 * will be skipped without any notification. Extra arguments will be skipped as well.
 *
 * Example:
 * Hello {name}! Good {time}! + ['name'=>'Member', 'time'=>'day']
 *
 * Output:
 * Hello Member! Good Day!
 *
 * This is common core function.
 *
 * @param string $format  Formatted string.
 * @param array  $values  Arguments (key => value). Will skip n
 * @param string $prefix  Value prefix, "{" by default.
 * @param string $postfix Value postfix "}" by default.
 * @return mixed
 */
function interpolate($format, array $values, $prefix = '{', $postfix = '}')
{
    if (empty($values))
    {
        return $format;
    }

    $replace = [];
    foreach ($values as $key => $value)
    {
        $value = (is_array($value) || $value instanceof \Closure) ? '' : $value;

        try
        {
            $value = is_object($value) ? (string)$value : $value;
        }
        catch (\Exception $e)
        {
            $value = '';
        }

        $replace[$prefix . $key . $postfix] = $value;
    }

    return strtr($format, $replace);
}
