<?php
/**
 * components
 *
 * @author    Wolfy-J
 */

namespace Spiral\ODM;

use MongoDB\Collection;
use Spiral\ODM\Exceptions\ODMException;

interface ODMInterface
{
    /**
     * Constants used in packed schema.
     */
    const D_INSTANTIATOR = 0;
    const D_SCHEMA       = 1;
    const D_DATABASE     = 2;
    const D_COLLECTION   = 3;

    /**
     * Get collection associated with given class.
     *
     * @param string $class
     *
     * @return Collection
     *
     * @throws ODMException
     */
    public function collection(string $class): Collection;

    /**
     * Instantiate document/model instance based on a given class name and fieldset. Some ODM
     * documents might return instances of their child if fields point to child model schema.
     *
     * @param string             $class
     * @param array|\ArrayAccess $fields
     *
     * @return CompositableInterface
     */
    public function instantiate(string $class, $fields = []): CompositableInterface;
}