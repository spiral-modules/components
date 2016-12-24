<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ODM\Accessors;

use Spiral\ODM\ODM;

/**
 * Provides ability to store array of MongoId (ObjectID).
 */
class ObjectIDsArray extends AbstractArray
{
    /**
     * {@inheritdoc}
     */
    protected function filterValue($value)
    {
        return ODM::mongoID($value);
    }
}