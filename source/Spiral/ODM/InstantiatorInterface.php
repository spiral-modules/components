<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ODM;

use Spiral\ODM\Exceptions\ODMException;

/**
 * Instantiate class based on a given field set.
 */
interface InstantiatorInterface
{
    /**
     * Method must detect and construct appropriate class instance based on a given fields.
     *
     * @param array|\ArrayAccess $fields
     * @param bool               $inheritance Set to false to disable inheritance.
     *
     * @return mixed
     *
     * @throws ODMException
     */
    public function instantiate($fields, bool $inheritance = true);
}