<?php
/**
 * components
 *
 * @author    Wolfy-J
 */

namespace Spiral\ORM;

use Spiral\ORM\Exceptions\InstantionException;

/**
 * Instantiates ORM entities.
 */
interface InstantiatorInterface
{
    /**
     * Identify set of fields with one unique value (usually primary key). Following identification
     * key will be used to store record in ORM entity cache.
     *
     * @param array|\ArrayAccess|mixed $fields
     *
     * @return string|null
     */
    public function identify($fields);

    /**
     * Method must detect and construct appropriate class instance based on a given fields.
     *
     * @param array|\ArrayAccess|mixed $fields
     * @param bool                     $filter When set to true values MUST be passed thought model
     *                                         filters to ensure their types and filter any user
     *                                         data. This will slow down model creation.
     *
     * @return RecordInterface
     *
     * @throws InstantionException
     */
    public function make($fields, bool $filter = true): RecordInterface;
}