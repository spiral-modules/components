<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM;

use Spiral\Core\Traits\SaturateTrait;
use Spiral\Models\SchematicEntity;

class RecordEntity extends SchematicEntity
{
    use SaturateTrait;

    /**
     * Field format declares how entity must process magic setters and getters. Available values:
     * camelCase, tableize.
     */
    const FIELD_FORMAT = 'tableize';

    const SCHEMA   = [];
    const DEFAULTS = [];
    const INDEXES  = [];

    /**
     * List of fields must be hidden from publicFields() method.
     *
     * @see publicFields()
     *
     * @var array
     */
    const HIDDEN = [];

    /**
     * Set of fields allowed to be filled using setFields() method.
     *
     * @see setFields()
     *
     * @var array
     */
    const FILLABLE = [];

    /**
     * List of fields not allowed to be filled by setFields() method. Replace with and empty array
     * to allow all fields.
     *
     * By default all entity fields are settable! Opposite behaviour has to be described in entity
     * child implementations.
     *
     * @see setFields()
     *
     * @var array|string
     */
    const SECURED = [];

    /**
     * @see setField()
     *
     * @var array
     */
    const SETTERS = [];

    /**
     * @see getField()
     *
     * @var array
     */
    const GETTERS = [];

    /**
     * Accessor used to mock field data and filter every request thought itself.
     *
     * @see getField()
     * @see setField()
     *
     * @var array
     */
    const ACCESSORS = [];

    /**
     * Entity states.
     */
    const STATE_NEW     = 0;
    const STATE_LOADED  = 1;
    const STATE_UPDATED = 2;
    const STATE_DELETED = 3;

    /**
     * Indicates current entity state.
     *
     * @var int
     */
    private $state = self::STATE_NEW;

    public function __construct(
        array $fields,
        int $state = self::STATE_NEW,
        ORMInterface $orm = null
    ) {
        parent::__construct($fields, ['schema goes here']);
    }
}