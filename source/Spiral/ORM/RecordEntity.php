<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM;

use Spiral\Models\SchematicEntity;

class RecordEntity extends SchematicEntity
{
    /**
     * Set of schema sections needed to describe entity behaviour.
     */
    const SH_DEFAULTS  = 0;
    //const SH_NULLABLE  = 6;
    const SH_RELATIONS = 7;

    /**
     * Constants used to declare indexes in record schema.
     *
     * @see Record::$indexes
     */
    const INDEX  = 1000;            //Default index type
    const UNIQUE = 2000;            //Unique index definition

    const SCHEMA   = [];
    const DEFAULTS = [];
    const INDEXES  = [];

    public function isLoaded(): bool
    {
        return true;
    }

    public function primaryKey()
    {

    }
}