<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM;

use Spiral\Database\Entities\Table;
use Spiral\Models\IdentifiedInterface;
use Spiral\ORM\Entities\RecordSelector;
use Spiral\ORM\Exceptions\ORMException;

/**
 * ORM component is very similar to ODM in some aspects, however data is handled differently so
 * implementations are split on low level and only utilize DataEntities as common point.
 */
interface ORMInterface
{
    /**
     * Constants used in packed schema.
     */
    const R_INSTANTIATOR = 0;
    const R_SCHEMA       = 1;
    const R_SOURCE_CLASS = 2;
    const R_DATABASE     = 3;
    const R_TABLE        = 4;
    const R_RELATIONS    = 5;

    /**
     * Define property from ORM schema. Attention, ORM will automatically load schema if it's empty.
     *
     * Example:
     * $odm->define(User::class, ORM::D_INSTANTIATOR);
     *
     * @param string $class
     * @param int    $property See ORM constants.
     *
     * @return mixed
     *
     * @throws ORMException
     */
    public function define(string $class, int $property);

    /**
     * Get RecordSelector for a given class.
     *
     * Example:
     * Admin extends User
     * $orm->selector(Admin::class)->getClass() == User::class
     *
     * @param string $class
     *
     * @return RecordSelector
     */
    //public function selector(string $class): RecordSelector;

    /**
     * Get table associated with given class.
     *
     * @param string $class
     *
     * @return Table
     *
     * @throws ORMException
     */
    public function table(string $class): Table;

    /**
     * Instantiate record/model instance based on a given class name and fieldset.
     *
     * @param string                   $class
     * @param array|\ArrayAccess|mixed $fields
     * @param bool                     $filter When set to true values MUST be passed thought model
     *                                         filters to ensure their types and filter any user
     *                                         data. This will slow down model creation.
     * @param bool                     $cache  Add entity into EntityCache.
     *
     * @return RecordInterface
     */
    public function make(
        string $class,
        $fields = [],
        bool $filter = true,
        bool $cache = false
    ): RecordInterface;
}