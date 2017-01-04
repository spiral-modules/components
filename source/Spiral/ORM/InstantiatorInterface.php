<?php
/**
 * components
 *
 * @author    Wolfy-J
 */

namespace Spiral\ORM;

use Spiral\Models\ActiveEntityInterface;
use Spiral\ORM\Exceptions\InstantionException;

/**
 * Instantiates ORM entities.
 */
interface InstantiatorInterface
{
    /**
     * Method must detect and construct appropriate class instance based on a given fields.
     *
     * @param array|\ArrayAccess|mixed $fields
     * @param bool                     $filter When set to true values MUST be passed thought model
     *                                         filters to ensure their types and filter any user
     *                                         data. This will slow down model creation.
     *
     * @return ActiveEntityInterface
     *
     * @throws InstantionException
     */
    public function make($fields, bool $filter = true): ActiveEntityInterface;
}