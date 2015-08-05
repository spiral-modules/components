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
 * Interpolate string with given parameters, most used function there.
 *
 * Input: Hello {name}! Good {time}! + ['name' => 'Member', 'time' => 'day']
 * Output: Hello Member! Good Day!
 *
 * @param string $string
 * @param array  $values  Arguments (key => value). Will skip unknown names.
 * @param string $prefix  Placeholder prefix, "{" by default.
 * @param string $postfix Placeholder postfix, "}" by default.
 * @return mixed
 */
function interpolate($string, array $values, $prefix = '{', $postfix = '}')
{
    $replaces = [];
    foreach ($values as $key => $value) {
        $value = (is_array($value) || $value instanceof \Closure) ? '' : $value;

        try {
            //Object as string
            $value = is_object($value) ? (string)$value : $value;
        } catch (\Exception $e) {
            $value = '';
        }

        $replaces[$prefix . $key . $postfix] = $value;
    }

    return strtr($string, $replaces);
}
