<?php
/**
 * components
 *
 * @author    Wolfy-J
 */

namespace Spiral\ORM;

use Spiral\ORM\Exceptions\InstantionException;

/**
 * ORM provides ability to instantiate any custom class.
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
     * @return mixed
     *
     * @throws InstantionException
     */
    public function instantiate($fields, bool $filter = true);
}