<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */
namespace Spiral\ODM;

use Spiral\ODM\Exceptions\InstantionException;

/**
 * Instantiate class based on a given field set.
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
     * @return CompositableInterface
     *
     * @throws InstantionException
     */
    public function make($fields, bool $filter = true): CompositableInterface;
}