<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ODM\Accessors;

/**
 * Provides ability to store array of strings.
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