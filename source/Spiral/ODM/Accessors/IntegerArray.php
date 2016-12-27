<?php
/**
 * Spiral Framework, Core Components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ODM\Accessors;

/**
 * Allows to store only integer values.
 *
 * Attention, array will be saved as one big $set operation in case when multiple atomic
 * operations applied to it (not supported by Mongo).
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