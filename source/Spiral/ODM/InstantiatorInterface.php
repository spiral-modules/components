<?php
/**
 * components
 *
 * @author    Wolfy-J
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
     *
     * @return CompositableInterface
     *
     * @throws InstantionException
     */
    public function instantiate($fields): CompositableInterface;
}