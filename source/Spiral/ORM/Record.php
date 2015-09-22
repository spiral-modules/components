<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright �2009-2015
 */
namespace Spiral\ORM;

use Spiral\Database\Entities\Table;
use Spiral\Database\Exceptions\QueryException;
use Spiral\Models\AccessorInterface;
use Spiral\Models\ActiveEntityInterface;
use Spiral\Models\EntityInterface;
use Spiral\Models\Exceptions\AccessorExceptionInterface;
use Spiral\Models\SchematicEntity;
use Spiral\ORM\Exceptions\RecordException;
use Spiral\ORM\Exceptions\RelationException;
use Spiral\ORM\Traits\FindTrait;
use Spiral\Validation\ValidatesInterface;

/**
 * Record is base data entity for ORM component, it used to describe related table schema,
 * filters, validations and relations to other records. You can count Record class as ActiveRecord
 * pattern. ORM component will automatically analyze existed Documents and create cached version of
 * their schema.
 *
 * @TODO: Add ability to set primary key manually, for example fpr uuid like fields.
 */
class Record extends SchematicEntity implements ActiveEntityInterface
{
    /**
     * Static find functions.
     */
    use FindTrait;

    /**
     * Field format declares how entity must process magic setters and getters. Available values:
     * camelCase, tableize.
     */
    const FIELD_FORMAT = 'tableize';

    /**
     * We are going to inherit parent validation rules, this will let spiral translator know about
     * it and merge i18n messages.
     *
     * @see TranslatorTrait
     */
    const I18N_INHERIT_MESSAGES = true;

    /**
     * Indication that save methods must be validated by default, can be altered by calling save
     * method with user arguments.
     */
    const VALIDATE_SAVE = true;

    /**
     * ORM records are be divided by two sections: active and passive records. When record is active
     * ORM allowed to modify associated record table using declared schema and created relations.
     *
     * Passive records (ACTIVE_SCHEMA = false) however can only read table schema from database and
     * forbidden to do any schema modification either by record or by relations.
     *
     * You can use ACTIVE_SCHEMA = false in cases where you need to create an ActiveRecord for
     * existed table.
     *
     * @see RecordSchema
     * @see \Spiral\ORM\Entities\SchemaBuilder
     */
    const ACTIVE_SCHEMA = true;

    /**
     * Indication that record were deleted.
     */
    const DELETED = 900;

    /**
     * Default ORM relation types, see ORM configuration and documentation for more information,
     * i had to remove 200 lines of comments to make record little bit smaller.
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
     * @see Record::$schema
     * @see RelationSchemaInterface
     */
    const INVERSE           = 1001; //Relation should be inverted to parent record
    const CONSTRAINT        = 1002; //Relation should create foreign keys (default)
    const CONSTRAINT_ACTION = 1003; //Default relation foreign key delete/update action (CASCADE)
    const CREATE_PIVOT      = 1004; //Many-to-Many should create pivot table automatically (default)
    const NULLABLE          = 1005; //Relation can be nullable (default)
    const CREATE_INDEXES    = 1006; //Indication that relation is allowed to create required indexes
    const MORPHED_ALIASES   = 1007; //Aliases for morphed sub-relations

    /**
     * Relations marked as embedded will be automatically saved/validated with parent model. In
     * addition such models data can be set using setFields method (only for ONE relations).
     *
     * @see setFields()
     * @see save()
     * @see validate()
     */
    const EMBEDDED_RELATION = 1008;

    /**
     * Constants used to declare indexes in record schema.
     *
     * @see Record::$indexes
     */
    const INDEX  = 1000;            //Default index type
    const UNIQUE = 2000;            //Unique index definition

    /**
     * Errors in relations and acessors.
     *
     * @var array
     */
    private $nestedErrors = [];

    /**
     * Indicates that record data were loaded from database (not recently created).
     *
     * @var bool
     */
    private $loaded = false;

    /**
     * Schema provided by ORM component.
     *
     * @var array
     */
    private $ormSchema = [];

    /**
     * SolidState will force record data to be saved as one big update set without any generating
     * separate update statements for changed columns.
     *
     * @var bool
     */
    private $solidState = false;

    /**
     * Populated when record loaded using many-to-many connection. Property will include every
     * column of connection row in pivot table.
     *
     * @see setContext()
     * @see getPivot();
     * @var array
     */
    private $pivotData = [];

    /**
     * Record field updates (changed values).
     *
     * @var array
     */
    private $updates = [];

    /**
     * Constructed and pre-cached set of record relations. Relation will be in a form of data array
     * to be created on demand.
     *
     * @see relation()
     * @see __call()
     * @see __set()
     * @see __get()
     * @var RelationInterface[]|array
     */
    private $relations = [];

    /**
     * Table name (without database prefix) record associated to, RecordSchema will generate table
     * name automatically using class name, however i'm strongly recommend to declare table name
     * manually as it gives more readable code.
     *
     * @var string
     */
    protected $table = null;

    /**
     * Database name/id where record table located in. By default database will be used if nothing
     * else is specified.
     *
     * @var string|null
     */
    protected $database = null;

    /**
     * Set of indexes to be created for associated record table, indexes only created when record is
     * not abstract and has active schema set to true.
     *
     * Use constants INDEX and UNIQUE to describe indexes, you can also create compound indexes:
     * protected $indexes = [
     *      [self::UNIQUE, 'email'],
     *      [self::INDEX, 'board_id'],
     *      [self::INDEX, 'board_id', 'check_id']
     * ];
     *
     * @var array
     */
    protected $indexes = [];

    /**
     * Record relations and columns can be described in one place - record schema.
     * Attention: while defining table structure make sure that ACTIVE_SCHEMA constant is set to t
     * rue.
     *
     * Example:
     * protected $schema = [
     *      'id'        => 'primary',
     *      'name'      => 'string',
     *      'biography' => 'text'
     * ];
     *
     * You can pass additional options for some of your columns:
     * protected $schema = [
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
     * protected $schema = [
     *      //Table schema
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
    protected $schema = [];

    /**
     * Default field values.
     *
     * @var array
     */
    protected $defaults = [];

    /**
     * @invisible
     * @var ORM
     */
    protected $orm = null;

    /**
     * Due setContext() method and entity cache of ORM any custom initiation code in constructor
     * must not depends on database data.
     *
     * @see Component::staticContainer()
     * @see setContext
     * @param array      $data
     * @param bool|false $loaded
     * @param ORM|null   $orm
     * @param array      $ormSchema
     */
    public function __construct(
        array $data = [],
        $loaded = false,
        ORM $orm = null,
        array $ormSchema = []
    ) {
        $this->loaded = $loaded;

        //We can use global container as fallback if no default values were provided
        $this->orm = self::saturate($orm, ORM::class);
        $this->ormSchema = !empty($ormSchema) ? $ormSchema : $this->orm->getSchema(static::class);
        parent::__construct($this->ormSchema);

        static::initialize();

        if (isset($data[ORM::PIVOT_DATA])) {
            $this->pivotData = $data[ORM::PIVOT_DATA];
            unset($data[ORM::PIVOT_DATA]);
        }

        foreach (array_intersect_key($data,
            $this->ormSchema[ORM::M_RELATIONS]) as $name => $relation) {
            $this->relations[$name] = $relation;
            unset($data[$name]);
        }

        //Merging with default values
        $this->fields = $data + $this->ormSchema[ORM::M_COLUMNS];

        if (!$this->isLoaded()) {
            //Non loaded records should be in solid state by default and require initial validation
            $this->solidState(true)->invalidate();
        }
    }

    /**
     * Record context must be updated in cases where single record instance can be accessed from
     * multiple places, context will not change record fields but might overwrite pivot data or
     * clarify loaded relations.
     *
     * @param array $context
     * @return $this
     */
    public function setContext(array $context)
    {
        //Mounting context pivot data
        $this->pivotData = isset($context[ORM::PIVOT_DATA]) ? $context[ORM::PIVOT_DATA] : [];

        $relations = array_intersect_key($context, $this->ormSchema[ORM::M_RELATIONS]);
        foreach ($relations as $name => $relation) {
            if (!isset($this->relations[$name]) || is_array($this->relations[$name])) {
                //Does not exists and never requested before
                $this->relations[$name] = $relation;
                continue;
            }

            //We have to reset relation state to update context
            $this->relations[$name]->reset($relation, true);
        }

        /**
         * We are not going to update record fields.
         */

        return $this;
    }

    /**
     * Change record solid state. SolidState will force record data to be saved as one big update
     * set without any generating separate update statements for changed columns.
     *
     * Attention, you have to carefully use forceUpdate flag with records without primary keys due
     * update criteria (WHERE condition) can not be easy constructed for records with primary key.
     *
     * @param bool $solidState
     * @param bool $forceUpdate Mark all fields as changed to force update later.
     * @return $this
     */
    public function solidState($solidState, $forceUpdate = false)
    {
        $this->solidState = $solidState;

        if ($forceUpdate) {
            if ($this->ormSchema[ORM::M_PRIMARY_KEY]) {
                $this->updates = $this->getCriteria();
            } else {
                $this->updates = $this->ormSchema[ORM::M_COLUMNS];
            }
        }

        return $this;
    }

    /**
     * Is record is solid state?
     *
     * @see solidState()
     * @return bool
     */
    public function isSolid()
    {
        return $this->solidState;
    }

    /**
     * Role name used in morphed relations to detect outer record table and class. It usually built
     * based on record class name, but can be defined using ROLE_NAME constant.
     *
     * @return string
     */
    public function recordRole()
    {
        return $this->ormSchema[ORM::M_ROLE_NAME];
    }

    /**
     * Record primary key value (if any).
     *
     * @return mixed
     */
    public function primaryKey()
    {
        return isset($this->fields[$this->ormSchema[ORM::M_PRIMARY_KEY]])
            ? $this->fields[$this->ormSchema[ORM::M_PRIMARY_KEY]]
            : null;
    }

    /**
     * Is record were fetched from databases or recently created?
     *
     * @return bool
     */
    public function isLoaded()
    {
        return (bool)$this->loaded && !$this->isDeleted();
    }

    /**
     * Indication that record data was deleted.
     *
     * @return bool
     */
    public function isDeleted()
    {
        return $this->loaded === self::DELETED;
    }

    /**
     * Pivot data associated with record instance, populated only in cases when record loaded using
     * Many-to-Many relation.
     *
     * @return array
     */
    public function getPivot()
    {
        return $this->pivotData;
    }

    /**
     * {@inheritdoc}
     *
     * @see   $fillable
     * @see   $secured
     * @see   isFillable()
     * @param array|\Traversable $fields
     * @param bool               $all Fill all fields including non fillable.
     * @return $this
     * @throws AccessorExceptionInterface
     * @event setFields($fields)
     */
    public function setFields($fields = [], $all = false)
    {
        parent::setFields($fields, $all);

        foreach ($fields as $name => $nested) {
            //We can fill data of embedded of relations (usually HAS ONE)
            if (!empty($this->ormSchema[ORM::M_RELATIONS][$name][ORM::R_DEFINITION][self::EMBEDDED_RELATION])) {
                //Getting relation instance
                $relation = $this->relation($name);

                //Getting related object
                $related = $relation->getRelated();
                if ($related instanceof EntityInterface) {
                    $related->setFields($nested);
                }
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * Must track field updates. In addition Records will not allow to set unknown field.
     *
     * @throws RecordException
     */
    public function setField($name, $value, $filter = true)
    {
        if (!array_key_exists($name, $this->fields)) {
            throw new RecordException("Undefined field '{$name}' in '" . static::class . "'.");
        }

        $original = isset($this->fields[$name]) ? $this->fields[$name] : null;
        if ($value === null && in_array($name, $this->ormSchema[ORM::M_NULLABLE])) {
            //We must bypass setters and accessors when null value assigned to nullable column
            $this->fields[$name] = null;
        } else {
            parent::setField($name, $value, $filter);
        }

        if (!array_key_exists($name, $this->updates)) {
            $this->updates[$name] = $original instanceof AccessorInterface
                ? $original->serializeData()
                : $original;
        }
    }

    /**
     * {@inheritdoc}
     *
     * Record will skip filtration for nullable fields.
     */
    public function getField($name, $default = null, $filter = true)
    {
        if (!array_key_exists($name, $this->fields)) {
            throw new RecordException("Undefined field '{$name}' in '" . static::class . "'.");
        }

        $value = $this->fields[$name];
        if ($value === null && in_array($name, $this->ormSchema[ORM::M_NULLABLE])) {
            if (!isset($this->ormSchema[ORM::M_MUTATORS]['accessor'][$name])) {
                //We can skip setters for null values, but not accessors
                return $value;
            }
        }

        return parent::getField($name, $default, $filter);
    }

    /**
     * Get or create record relation by it's name and pre-loaded (optional) set of data.
     *
     * @param string $name
     * @param mixed  $data
     * @param bool   $loaded
     * @return RelationInterface
     * @throws RelationException
     * @throws RecordException
     */
    public function relation($name, $data = null, $loaded = false)
    {
        if (array_key_exists($name, $this->relations)) {
            if (!is_object($this->relations[$name])) {
                $data = $this->relations[$name];
                unset($this->relations[$name]);

                //Loaded relation
                return $this->relation($name, $data, true);
            }

            //Already created
            return $this->relations[$name];
        }


        //Constructing relation
        if (!isset($this->ormSchema[ORM::M_RELATIONS][$name])) {
            throw new RecordException(
                "Undefined relation {$name} in record " . static::class . "."
            );
        }

        $relation = $this->ormSchema[ORM::M_RELATIONS][$name];

        return $this->relations[$name] = $this->orm->relation(
            $relation[ORM::R_TYPE],
            $this,
            $relation[ORM::R_DEFINITION],
            $data,
            $loaded
        );
    }

    /**
     * {@inheritdoc}
     *
     * Create or update record data in database. Record will validate all EMBEDDED and loaded
     * relations.
     *
     * @see   sourceTable()
     * @see   getCriteria()
     * @param bool|null $validate  Overwrite default option declared in VALIDATE_SAVE to force or
     *                             disable validation before saving.
     * @return bool
     * @throws RecordException
     * @throws QueryException
     * @event saving()
     * @event saved()
     * @event updating()
     * @event updated()
     */
    public function save($validate = null)
    {
        if (is_null($validate)) {
            $validate = static::VALIDATE_SAVE;
        }

        if ($validate && !$this->isValid()) {
            return false;
        }

        //Primary key field name
        $primaryKey = $this->ormSchema[ORM::M_PRIMARY_KEY];
        if (!$this->isLoaded()) {
            $this->fire('saving');

            //We will need to support records with multiple primary keys in future
            unset($this->fields[$primaryKey]);

            //Creating
            $lastID = $this->sourceTable()->insert($this->fields = $this->serializeData());
            if (!empty($primaryKey)) {
                //Updating record primary key
                $this->fields[$primaryKey] = $lastID;
            }

            $this->loaded = true;
            $this->fire('saved');

            //Saving record to entity cache if we have space for that
            $this->orm->registerEntity($this, false);
        } elseif ($this->solidState || $this->hasUpdates()) {
            $this->fire('updating');

            //Updating
            $this->sourceTable()->update($this->compileUpdates(), $this->getCriteria())->run();

            $this->fire('updated');
        }

        $this->flushUpdates();

        foreach ($this->relations as $name => $relation) {
            if (!$relation instanceof RelationInterface) {
                //Was never constructed
                continue;
            }

            if (
                !empty($this->ormSchema[ORM::M_RELATIONS][$name][ORM::R_DEFINITION][self::EMBEDDED_RELATION])
                && !$relation->saveAssociation($validate)
            ) {
                throw new RecordException("Unable to save relation '{$name}'.");
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @event deleting()
     * @event deleted()
     */
    public function delete()
    {
        $this->fire('deleting');

        if ($this->isLoaded()) {
            $this->sourceTable()->delete($this->getCriteria())->run();
        }

        $this->fields = $this->ormSchema[ORM::M_COLUMNS];
        $this->loaded = self::DELETED;

        $this->fire('deleted');
    }

    /**
     * {@inheritdoc}
     *
     * @param string $field Specific field name to check for updates.
     */
    public function hasUpdates($field = null)
    {
        if (empty($field)) {
            if (!empty($this->updates)) {
                return true;
            }

            foreach ($this->fields as $field => $value) {
                if ($value instanceof RecordAccessorInterface && $value->hasUpdates()) {
                    return true;
                }
            }

            return false;
        }

        if (array_key_exists($field, $this->updates)) {
            return true;
        }

        $value = $this->getField($field);
        if ($value instanceof RecordAccessorInterface && $value->hasUpdates()) {
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function flushUpdates()
    {
        $this->updates = [];

        foreach ($this->fields as $value) {
            if ($value instanceof RecordAccessorInterface) {
                $value->flushUpdates();
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function __isset($name)
    {
        if (isset($this->ormSchema[ORM::M_RELATIONS][$name])) {
            return !empty($this->relation($name)->getRelated());
        }

        return parent::__isset($name);
    }

    /**
     * {@inheritdoc}
     *
     * @throws RecordException
     */
    public function __unset($offset)
    {
        throw new RecordException("Records fields can not be unsetted.");
    }

    /**
     * {@inheritdoc}
     *
     * @see relation()
     */
    public function __get($offset)
    {
        if (isset($this->ormSchema[ORM::M_RELATIONS][$offset])) {
            //Bypassing call to relation
            return $this->relation($offset)->getRelated();
        }

        return $this->getField($offset, true);
    }

    /**
     * {@inheritdoc}
     *
     * @see relation()
     */
    public function __set($offset, $value)
    {
        if (isset($this->ormSchema[ORM::M_RELATIONS][$offset])) {
            //Bypassing call to relation
            $this->relation($offset)->associate($value);

            return;
        }

        $this->setField($offset, $value, true);
    }

    /**
     * Direct access to relation by it's name.
     *
     * @see relation()
     * @param string $method
     * @param array  $arguments
     * @return RelationInterface|mixed|AccessorInterface
     */
    public function __call($method, array $arguments)
    {
        if (isset($this->ormSchema[ORM::M_RELATIONS][$method])) {
            return $this->relation($method);
        }

        //See FIELD_FORMAT constant
        return parent::__call($method, $arguments);
    }

    /**
     * @return Object
     */
    public function __debugInfo()
    {
        $info = [
            'table'     => $this->ormSchema[ORM::M_DB] . '/' . $this->ormSchema[ORM::M_TABLE],
            'pivotData' => $this->pivotData,
            'fields'    => $this->getFields(),
            'errors'    => $this->getErrors()
        ];

        if (empty($this->pivotData)) {
            unset($info['pivotData']);
        }

        return (object)$info;
    }

    /**
     * {@inheritdoc}
     */
    protected function container()
    {
        return $this->orm->container();
    }

    /**
     * Create set of fields to be sent to UPDATE statement.
     *
     * @see save()
     * @return array
     */
    protected function compileUpdates()
    {
        if (!$this->hasUpdates() && !$this->isSolid()) {
            return [];
        }

        if ($this->isSolid()) {
            return $this->solidUpdate();
        }

        $updates = [];
        foreach ($this->fields as $field => $value) {
            if ($value instanceof RecordAccessorInterface) {
                if ($value->hasUpdates()) {
                    $updates[$field] = $value->compileUpdates($field);
                    continue;
                }

                //Will be handled as normal update if needed
                $value = $value->serializeData();
            }

            if (array_key_exists($field, $this->updates)) {
                $updates[$field] = $value;
            }
        }

        //Primary key should not present in update set
        unset($updates[$this->ormSchema[ORM::M_PRIMARY_KEY]]);

        return $updates;
    }

    /**
     * Get associated Database\Table instance.
     *
     * @see save()
     * @see delete()
     * @return Table
     */
    protected function sourceTable()
    {
        return $this->orm->dbalDatabase($this->ormSchema[ORM::M_DB])->table(
            $this->ormSchema[ORM::M_TABLE]
        );
    }

    /**
     * Get WHERE array to be used to perform record data update or deletion. Usually will include
     * record primary key.
     *
     * @return array
     */
    protected function getCriteria()
    {
        if (!empty($this->ormSchema[ORM::M_PRIMARY_KEY])) {
            return [$this->ormSchema[ORM::M_PRIMARY_KEY] => $this->primaryKey()];
        }

        //We have to serialize record data
        return $this->updates + $this->serializeData();
    }

    /**
     * {@inheritdoc}
     */
    public function isValid()
    {
        $this->validate();

        return empty($this->errors) && empty($this->nestedErrors);
    }

    /**
     * {@inheritdoc}
     */
    public function getErrors($reset = false)
    {
        return parent::getErrors($reset) + $this->nestedErrors;
    }

    /**
     * {@inheritdoc}
     *
     * Will validate every loaded and embedded relation.
     */
    protected function validate($reset = false)
    {
        $this->nestedErrors = [];

        //Validating all compositions/accessors
        foreach ($this->fields as $field => $value) {
            //Ensuring value state
            $value = $this->getField($field);
            if (!$value instanceof ValidatesInterface) {
                continue;
            }

            if (!$value->isValid()) {
                $this->nestedErrors[$field] = $value->getErrors($reset);
            }
        }

        //We have to validate relations before saving them
        foreach ($this->relations as $name => $relation) {
            if (!$relation instanceof ValidatesInterface) {
                //Never constructed
                continue;
            }

            if (
                //I'm not very proud of length of this condition
                !empty($this->ormSchema[ORM::M_RELATIONS][$name][ORM::R_DEFINITION][self::EMBEDDED_RELATION])
                && !$relation->isValid()
            ) {
                $this->nestedErrors[$name] = $relation->getErrors($reset);
            }
        }

        parent::validate($reset);

        return empty($this->errors + $this->nestedErrors);
    }

    /**
     * {@inheritdoc}
     *
     * @see   Component::staticContainer()
     * @param array $fields Record fields to set, will be passed thought filters.
     * @param ORM   $orm    ORM component, global container will be called if not instance provided.
     * @event created()
     */
    public static function create($fields = [], ORM $orm = null)
    {
        /**
         * @var self $record
         */
        $record = new static([], false, self::saturate($orm, ORM::class));

        //Forcing validation (empty set of fields is not valid set of fields)
        $record->setFields($fields)->fire('created');

        return $record;
    }

    /**
     * Full structure update.
     *
     * @return array
     */
    private function solidUpdate()
    {
        $updates = [];
        foreach ($this->fields as $field => $value) {
            if ($value instanceof RecordAccessorInterface && $value->hasUpdates()) {
                if ($value->hasUpdates()) {
                    $updates[$field] = $value->compileUpdates($field);
                } else {
                    $updates[$field] = $value->serializeData();
                }
                continue;
            }

            $updates[$field] = $value;
        }

        return $updates;
    }
}