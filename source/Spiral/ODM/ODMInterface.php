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
    const D_INSTANTIATOR  = 0;
    const D_PRIMARY_CLASS = 1;
    const D_SCHEMA        = 2;
    const D_DATABASE      = 3;
    const D_COLLECTION    = 4;

    /**
     * Define property from ODM schema. Attention, ODM will automatically load schema if it's empty.
     *
     * Example:
     * $odm->define(User::class, ODM::D_INSTANTIATOR);
     *
     * @param string $class
     * @param int    $property See ODM constants.
     *
     * @return mixed
     *
     * @throws ODMException
     */
    public function define(string $class, int $property);

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
     * @param string                   $class
     * @param array|\ArrayAccess|mixed $fields
     *
     * @return CompositableInterface
     */
    public function instantiate(string $class, $fields = []): CompositableInterface;
}