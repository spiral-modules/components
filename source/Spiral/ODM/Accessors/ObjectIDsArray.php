<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */
namespace Spiral\ODM\Accessors;

use Spiral\ODM\ODM;

/**
 * Provides ability to store array of MongoId (ObjectID).
 *
 * Attention, array will be saved as one big $set operation in case when multiple atomic
 * operations applied to it (not supported by Mongo).
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