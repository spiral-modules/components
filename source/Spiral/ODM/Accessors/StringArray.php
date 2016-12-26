<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ODM\Accessors;

/**
 * Provides ability to store array of strings.
 *
 * Attention, array will be saved as one big $set operation in case when multiple atomic
 * operations applied to it (not supported by Mongo).
 */
class StringArray extends AbstractArray
{
    /**
     * {@inheritdoc}
     */
    protected function filterValue($value)
    {
        if (!is_string($value)) {
            return null;
        }

        return strval($value);
    }
}