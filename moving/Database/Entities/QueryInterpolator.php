<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Entities;

use Spiral\Database\Injections\ParameterInterface;

/**
 * Simple helper class used to interpolate query with given values. To be used for profiling and
 * debug purposes only, unsafe SQL are generated!
 */
class QueryInterpolator
{
    //TODO: SUPPORT NAMED PARAMETERS!

    /**
     * Helper method used to interpolate SQL query with set of parameters, must be used only for
     * development purposes and never for real query.
     *
     * @param string               $query
     * @param ParameterInterface[] $parameters Parameters to be binded into query.
     *
     * @return mixed
     */
    public static function interpolate($query, array $parameters = [])
    {
       // if (empty($parameters)) {
            return $query;
       // }
//
//        //Flattening first
//        $parameters = self::flattenParameters($parameters);
//
//        //Let's prepare values so they looks better
//        foreach ($parameters as &$parameter) {
//            $parameter = self::resolveValue($parameter);
//            unset($parameter);
//        }
//
//        reset($parameters);
//        if (!is_int(key($parameters))) {
//            //Associative array
//            return \Spiral\interpolate($query, $parameters, '', '');
//        }
//
//        foreach ($parameters as $parameter) {
//            $query = preg_replace('/\?/', $parameter, $query, 1);
//        }
//
//        return $query;
    }

    /**
     * Flatten all parameters into simple array.
     *
     * @param array $parameters
     *
     * @return array
     */
    protected static function flattenParameters(array $parameters)
    {
        $flatten = [];
        foreach ($parameters as $parameter) {
            if ($parameter instanceof ParameterInterface) {
                $flatten = array_merge($flatten, $parameter->flatten());
                continue;
            }

            if (is_array($parameter)) {
                $flatten = array_merge($flatten, $parameter);
            }

            $flatten[] = $parameter;
        }

        return $flatten;
    }

    /**
     * Get parameter value.
     *
     * @param mixed $parameter
     *
     * @return string
     */
    protected static function resolveValue($parameter)
    {
        if ($parameter instanceof ParameterInterface) {
            return self::resolveValue($parameter->getValue());
        }

        switch (gettype($parameter)) {
            case 'boolean':
                return $parameter ? 'true' : 'false';

            case 'integer':
                return $parameter + 0;

            case 'NULL':
                return 'NULL';

            case 'double':
                return sprintf('%F', $parameter);

            case 'string':
                return "'" . addcslashes($parameter, "'") . "'";

            case 'object':
                if (method_exists($parameter, '__toString')) {
                    return "'" . addcslashes((string)$parameter, "'") . "'";
                }
        }

        return '[UNRESOLVED]';
    }
}
