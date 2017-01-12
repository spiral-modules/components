<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM;

use Spiral\Core\Traits\SaturateTrait;
use Spiral\Models\SchematicEntity;
use Spiral\Models\Traits\SolidableTrait;

/**
 * Provides ActiveRecord-less abstraction for carried data with ability to automatically apply
 * setters, getters, generate update, insert and delete sequences and access nested relations.
 *
 * Class implementations statically analyzed to define DB schema.
 *
 * @see RecordEntity::SCHEMA
 */
abstract class RecordEntity extends SchematicEntity
{
    use SaturateTrait, SolidableTrait;

    /**
     * Set of schema sections needed to describe entity behaviour.
     */
    const SH_PRIMARIES = 0;
    const SH_DEFAULTS  = 1;

    /**
     * Default ORM relation types, see ORM configuration and documentation for more information.
     *
     * @see RelationSchemaInterface
     * @see RelationSchema
     */
    const HAS_ONE      = 101;
    const HAS_MANY     = 102;
    const BELONGS_TO   = 103;
    const MANY_TO_MANY = 104;

    /**
     * Morphed relation types are usually created by inversion or equivalent of primary relation
     * types.
     *
     * @see RelationSchemaInterface
     * @see RelationSchema
     * @see MorphedRelation
     */
    const BELONGS_TO_MORPHED = 108;
    const MANY_TO_MORPHED    = 109;

    /**
     * Constants used to declare relations in record schema, used in normalized relation schema.
     *
     * @see RelationSchemaInterface
     */
    const OUTER_KEY         = 901; //Outer key name
    const INNER_KEY         = 902; //Inner key name
    const MORPH_KEY         = 903; //Morph key name
    const PIVOT_TABLE       = 904; //Pivot table name
    const PIVOT_COLUMNS     = 905; //Pre-defined pivot table columns
    const PIVOT_DEFAULTS    = 906; //Pre-defined pivot table default values
    const THOUGHT_INNER_KEY = 907; //Pivot table options
    const THOUGHT_OUTER_KEY = 908; //Pivot table options
    const WHERE             = 909; //Where conditions
    const WHERE_PIVOT       = 910; //Where pivot conditions

    /**
     * Additional constants used to control relation schema behaviour.
     *
     * @see RecordEntity::SCHEMA
     * @see RelationSchemaInterface
     */
    const INVERSE           = 1001; //Relation should be inverted to parent record
    const CREATE_CONSTRAINT = 1002; //Relation should create foreign keys (default)
    const CONSTRAINT_ACTION = 1003; //Default relation foreign key delete/update action (CASCADE)
    const CREATE_PIVOT      = 1004; //Many-to-Many should create pivot table automatically (default)
    const NULLABLE          = 1005; //Relation can be nullable (default)
    const CREATE_INDEXES    = 1006; //Indication that relation is allowed to create required indexes
    const MORPHED_ALIASES   = 1007; //Aliases for morphed sub-relations

    /**
     * Set of columns to be used in relation (attention, make sure that loaded records are set as
     * NON SOLID if you planning to modify their data).
     */
    const RELATION_COLUMNS = 1009;

    /**
     * Constants used to declare indexes in record schema.
     *
     * @see Record::INDEXES
     */
    const INDEX  = 1000;            //Default index type
    const UNIQUE = 2000;            //Unique index definition

    /**
     * Record relations and columns can be described in one place - record schema.
     * Attention: while defining table structure make sure that ACTIVE_SCHEMA constant is set to t
     * rue.
     *
     * Example:
     * const SCHEMA = [
     *      'id'        => 'primary',
     *      'name'      => 'string',
     *      'biography' => 'text'
     * ];
     *
     * You can pass additional options for some of your columns:
     * const SCHEMA = [
     *      'pinCode' => 'string(128)',         //String length
     *      'status'  => 'enum(active, hidden)', //Enum values
     *      'balance' => 'decimal(10, 2)'       //Decimal size and precision
     * ];
     *
     * Every created column will be stated as NOT NULL with forced default value, if you want to
     * have nullable columns, specify special data key: protected $schema = [
     *      'name'      => 'string, nullable'
     * ];
     *
     * You can easily combine table and relations definition in one schema:
     * const SCHEMA = [
     *      'id'          => 'bigPrimary',
     *      'name'        => 'string',
     *      'email'       => 'string',
     *      'phoneNumber' => 'string(32)',
     *
     *      //Relations
     *      'profile'     => [
     *          self::HAS_ONE => 'Records\Profile',
     *          self::INVERSE => 'user'
     *      ],
     *      'roles'       => [
     *          self::MANY_TO_MANY => 'Records\Role',
     *          self::INVERSE => 'users'
     *      ]
     * ];
     *
     * @var array
     */
    const SCHEMA = [];

    /**
     * Default field values.
     *
     * @var array
     */
    const DEFAULTS = [];

    /**
     * Set of indexes to be created for associated record table, indexes only created when record is
     * not abstract and has active schema set to true.
     *
     * Use constants INDEX and UNIQUE to describe indexes, you can also create compound indexes:
     * const INDEXES = [
     *      [self::UNIQUE, 'email'],
     *      [self::INDEX, 'board_id'],
     *      [self::INDEX, 'board_id', 'check_id']
     * ];
     *
     * @var array
     */
    const INDEXES = [];

    /**
     * Record behaviour definition.
     *
     * @var array
     */
    private $recordSchema = [];

    /**
     * Record state.
     *
     * @var int
     */
    private $state;

    /**
     * Record field updates (changed values).
     *
     * @var array
     */
    private $changes = [];

    /**
     * Associated relation instances and/or initial loaded data.
     *
     * @var array
     */
    private $relations = [];

    /**
     * Parent ORM instance, responsible for relation initialization and lazy loading operations.
     *
     * @invisible
     * @var ORMInterface
     */
    protected $orm;

    /**
     * Initiate entity inside or outside of ORM scope using given fields and state.
     *
     * @param array             $fields
     * @param int               $state
     * @param ORMInterface|null $orm
     * @param array|null        $schema
     */
    public function __construct(
        $fields = [],
        int $state = ORMInterface::STATE_NEW,
        ORMInterface $orm = null,
        array $schema = null
    ) {//We can use global container as fallback if no default values were provided
        $this->orm = $this->saturate($orm, ORMInterface::class);

        //Use supplied schema or fetch one from ORM
        $this->recordSchema = !empty($schema) ? $schema : $this->orm->define(
            static::class,
            ORMInterface::R_SCHEMA
        );

        $this->state = $state;
        if ($this->state == ORMInterface::STATE_NEW) {
            //Non loaded records should be in solid state by default
            $this->solidState(true);
        }

        //todo: fetch relations

        parent::__construct($fields + $this->recordSchema[self::SH_DEFAULTS], $schema);
    }

    /**
     * @return array
     */
    public function __debugInfo()
    {
        return [
            'fields' => $this->getFields()
        ];
    }
}