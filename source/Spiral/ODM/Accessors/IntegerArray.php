<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ODM\Accessors;

/**
 * Allows to store only integer values.
 */
class IntegerArray extends AbstractArray
{
    /**
     * {@inheritdoc}
     */
    protected function filterValue($value)
    {
        if (!is_numeric($value)) {
            return null;
        }

        return intval($value);
    }
}