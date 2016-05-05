<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\ORM;

use Spiral\Database\Entities\Database;
use Spiral\Models\SchematicEntity;
use Spiral\ORM\Entities\RecordMapper;
use Spiral\ORM\Entities\RecordSource;
use Spiral\ORM\Exceptions\ORMException;

interface ORMInterface
{
    /**
     * Pivot table data location row fields.
     */
    const PIVOT_DATA = '@pivot';

    /**
     * Normalized record constants.
     */
    const M_ROLE_NAME   = 0;
    const M_SOURCE      = 1;
    const M_TABLE       = 2;
    const M_DB          = 3;
    const M_HIDDEN      = SchematicEntity::SH_HIDDEN;
    const M_SECURED     = SchematicEntity::SH_SECURED;
    const M_FILLABLE    = SchematicEntity::SH_FILLABLE;
    const M_MUTATORS    = SchematicEntity::SH_MUTATORS;
    const M_VALIDATES   = SchematicEntity::SH_VALIDATES;
    const M_COLUMNS     = 9;
    const M_NULLABLE    = 10;
    const M_RELATIONS   = 11;
    const M_PRIMARY_KEY = 12;

    /**
     * Normalized relation options.
     */
    const R_TYPE       = 0;
    const R_TABLE      = 1;
    const R_DEFINITION = 2;
    const R_DATABASE   = 3;

    /**
     * Get database by it's name from DatabaseManager associated with ORM component.
     *
     * @param string $database
     * @return Database
     */
    public function database($database);

    /**
     * Get given class schema or schema property. If property empty full schema array to be
     * returned.
     *
     * Example:
     * $orm->getSchema(User::class, ORM::R_DB); //Name of database associated with entity
     *
     * @param string $item
     * @param int    $property See property constants (all schema to be returned if value is
     *                         empty).
     *
     * @return array|mixed|string
     *
     * @throws ORMException
     */
    public function schema($item, $property = null);

    /**
     * Construct instance of Record or receive it from cache (if enabled). Only records with
     * declared primary key can be cached.
     *
     * @todo hydrate external class type?
     *
     * @param string $class Record class name.
     * @param array  $data  Record data including nested relations.
     * @param bool   $cache Add record to entity cache if enabled.
     *
     * @return RecordInterface
     */
    public function record($class, array $data, $cache = true);

    /**
     * Get ORM source for given class.
     *
     * @param string $class
     * @return RecordSource
     * @throws ORMException
     */
    public function source($class);

    /**
     * Get instance of RecordMapper responsible for save, update and delete operations with
     * records.
     *
     * @param string $class
     *
     * @return RecordMapper
     */
    public function mapper($class);
}