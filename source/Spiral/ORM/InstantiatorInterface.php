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
     * Identify set of fields with resulted unique value (usually primary key). Following
     * identification key will be used to store record in ORM entity cache.
     *
     * @param array|\ArrayAccess|mixed $fields
     *
     * @return string|null
     */
    public function identify($fields);

    /**
     * Method must detect and construct appropriate class instance based on a given fields. When
     * state set to NEW values MUST be filtered/typecasted before appearing in entity!
     *
     * @param array|\ArrayAccess|mixed $fields
     * @param int                      $state
     *
     * @return RecordInterface
     *
     * @throws InstantionException
     */
    public function make($fields, int $state): RecordInterface;
}