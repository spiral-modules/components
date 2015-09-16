<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM\Entities\Traits;

/**
 * Replaces {@} with valid table alias in array where.
 */
trait AliasTrait
{
    /**
     * Replace {@} in where statement with valid alias.
     *
     * @param string $alias
     * @param array  $where
     * @return array
     */
    protected function mountAlias($alias, array $where)
    {
        $result = [];

        foreach ($where as $key => $value) {
            if (strpos($key, '{@}') !== false) {
                $key = str_replace('{@}', $alias, $key);
            }

            if (is_array($value)) {
                $value = $this->mountAlias($alias, $where);
            }

            $result[$key] = $value;
        }

        return $result;
    }
}