<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM;

use Spiral\Database\Entities\Table;
use Spiral\ORM\Entities\RecordSelector;
use Spiral\ORM\Exceptions\ORMException;

/**
 * ORM component is very similar to ODM in some aspects, however data is handled differently so
 * implementations are split on low level and only utilize DataEntities as common point.
 */
interface ORMInterface
{
    /**
     * Entity states.
     */
    const STATE_NEW              = 0;
    const STATE_LOADED           = 1;
    const STATE_SCHEDULED_INSERT = 2;
    const STATE_SCHEDULED_UPDATE = 3;
    const STATE_SCHEDULED_DELETE = 4;

    /**
     * Constants used in packed schema.
     */
    const R_INSTANTIATOR = 0;
    const R_ROLE_NAME    = 1;
    const R_PRIMARIES    = 2;
    const R_SCHEMA       = 3;
    const R_SOURCE_CLASS = 4;
    const R_DATABASE     = 5;
    const R_TABLE        = 6;
    const R_RELATIONS    = 7;

    /**
     * Constants used in packed relation schemas.
     */
    const R_TYPE  = 0;
    const R_CLASS = 1;

    /**
     * Pivot table data location in Record fields. Pivot data only provided when record is loaded
     * using many-to-many relation.
     */
    const PIVOT_DATA = '@pivot';

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
     * @param string $class
     *
     * @return RecordSelector
     */
    public function selector(string $class): RecordSelector;

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
     * Instantiate record/model instance based on a given class name and fieldset. When state set
     * to NEW values MUST be filtered/typecasted before appearing in entity!
     *
     * @param string                   $class
     * @param array|\ArrayAccess|mixed $fields
     * @param int                      $state
     * @param bool                     $cache Add entity into EntityCache.
     *
     * @return RecordInterface
     */
    public function make(
        string $class,
        $fields = [],
        int $state = self::STATE_NEW,
        bool $cache = false
    ): RecordInterface;

    /**
     * Create instance of relation loader. Loader must receive target class name, relation schema
     * (packed) and orm itself.
     *
     * @param string $class
     * @param string $relation
     *
     * @return LoaderInterface
     *
     * @throws ORMException
     */
    public function makeLoader(string $class, string $relation): LoaderInterface;

    /**
     * Get instance of relation object used to represent related data.
     *
     * @param string $class
     * @param string $relation
     *
     * @return RelationInterface
     *
     * @throws ORMException
     */
    public function makeRelation(string $class, string $relation): RelationInterface;
}