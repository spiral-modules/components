<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\ORM;

use Spiral\Core\Component;
use Spiral\Core\Exceptions\SugarException;
use Spiral\Core\Traits\SaturateTrait;
use Spiral\Models\AccessorInterface;
use Spiral\Models\EntityInterface;
use Spiral\Models\Events\EntityEvent;
use Spiral\Models\Exceptions\AccessorExceptionInterface;
use Spiral\Models\SchematicEntity;
use Spiral\ORM\Exceptions\FieldException;
use Spiral\ORM\Exceptions\RecordException;
use Spiral\ORM\Exceptions\RelationException;
use Spiral\Validation\ValidatesInterface;

/**
 * RecordEntity is base data entity for ORM component, it used to describe related table schema,
 * filters, validations and relations to other records. You can count Record class as ActiveRecord
 * pattern. ORM component will automatically analyze existed Records and create cached version of
 * their schema.
 *
 * Configuration properties:
 * - schema
 * - defaults
 * - secured (* by default)
 * - fillable
 * - validates
 * - database
 * - table
 * - indexes
 *
 * @todo: Add ability to set primary key manually, for example fpr uuid like fields.
 */
class RecordEntity extends SchematicEntity implements RecordInterface
{
    use SaturateTrait;

    /**
     * We are going to inherit parent validation rules, this will let spiral translator know about
     * it and merge i18n messages.
     *
     * @see TranslatorTrait
     */
    const I18N_INHERIT_MESSAGES = true;

    /**
     * Field format declares how entity must process magic setters and getters. Available values:
     * camelCase, tableize.
     */
    const FIELD_FORMAT = 'tableize';

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
     * Errors in relations and accessors.
     *
     * @var array
     */
    private $nestedErrors = [];

    /**
     * SolidState will force record data to be saved as one big update set without any generating
     * separate update statements for changed columns.
     *
     * @var bool
     */
    private $solidState = false;

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
     *
     * @var RelationInterface[]|array
     */
    protected $relations = [];

    /**
     * @invisible
     *
     * @var ORMInterface|ORM
     */
    protected $orm = null;

    /**
     * {@inheritdoc}
     *
     * @param null|array $schema
     *
     * @throws SugarException
     */
    public function __construct(
        array $data = [],
        $loaded = false,
        ORMInterface $orm = null,
        array $schema = []
    ) {
        $this->loaded = $loaded;

        //We can use global container as fallback if no default values were provided
        $this->orm = $this->saturate($orm, ORMInterface::class);
        $this->ormSchema = !empty($schema) ? $schema : $this->orm->schema(static::class);

        $this->extractRelations($data);

        if (!$this->isLoaded()) {
            //Non loaded records should be in solid state by default and require initial validation
            $this->solidState(true)->invalidate();
        }

        parent::__construct($data + $this->ormSchema[ORMInterface::M_COLUMNS], $this->ormSchema);

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
     *
     * @return $this
     */
    public function solidState($solidState, $forceUpdate = false)
    {
        $this->solidState = $solidState;

        if ($forceUpdate) {
            if (!empty($this->ormSchema[ORMInterface::M_PRIMARY_KEY])) {
                $this->updates = $this->stateCriteria();
            } else {
                $this->updates = $this->ormSchema[ORMInterface::M_COLUMNS];
            }
        }

        return $this;
    }

    /**
     * Is record is solid state?
     *
     * @see solidState()
     *
     * @return bool
     */
    public function isSolid()
    {
        return $this->solidState;
    }

    /**
     * {@inheritdoc}
     */
    public function recordRole()
    {
        return $this->ormSchema[ORMInterface::M_ROLE_NAME];
    }

    /**
     * {@inheritdoc}
     */
    public function isLoaded()
    {
        return (bool)$this->loaded && !$this->isDeleted();
    }

    /**
     * {@inheritdoc}
     */
    public function isDeleted()
    {
        return $this->loaded === self::DELETED;
    }

    /**
     * {@inheritdoc}
     */
    public function primaryKey()
    {
        if (!$this->hasField(ORMInterface::M_PRIMARY_KEY)) {
            throw new RecordException("Record does not have determinated primary key");
        }

        return $this->getField('_id', null, false);
    }

    /**
     * {@inheritdoc}
     *
     * @see   $fillable
     * @see   $secured
     * @see   isFillable()
     *
     * @param array|\Traversable $fields
     * @param bool               $all Fill all fields including non fillable.
     *
     * @return $this
     *
     * @throws AccessorExceptionInterface
     */
    public function setFields($fields = [], $all = false)
    {
        parent::setFields($fields, $all);

        foreach ($fields as $name => $nested) {

            //We can fill data of embedded of relations (usually HAS ONE)
            if ($this->embeddedRelation($name) && !empty($relation = $this->relation($name))) {
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
     * Must track field updates.
     */
    public function setField($name, $value, $filter = true)
    {
        if (!$this->hasField($name)) {
            throw new FieldException("Undefined field '{$name}' in '" . static::class . "'");
        }

        //Original field value
        $original = $this->getField($name, null, false);

        if (is_null($value) && in_array($name, $this->ormSchema[ORM::M_NULLABLE])) {
            //Bypassing filter for nullable values
            parent::setField($name, null, false);
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
        if (!$this->hasField($name)) {
            throw new FieldException("Undefined field '{$name}' in '" . static::class . "'");
        }

        $value = parent::getField($name, $default, false);
        if ($value === null && in_array($name, $this->ormSchema[ORM::M_NULLABLE])) {
            if (!isset($this->ormSchema[ORMInterface::M_MUTATORS][self::MUTATOR_ACCESSOR][$name])) {
                //We can skip setters for null values, but not accessors
                return $value;
            }
        }

        return parent::getField($name, $default, $filter);
    }

    /**
     * {@inheritdoc}
     *
     * @see relation()
     */
    public function __get($offset)
    {
        if (isset($this->ormSchema[ORMInterface::M_RELATIONS][$offset])) {
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
        if (isset($this->ormSchema[ORMInterface::M_RELATIONS][$offset])) {
            //Bypassing call to relation
            $this->relation($offset)->associate($value);

            return;
        }

        $this->setField($offset, $value, true);
    }

    /**
     * {@inheritdoc}
     *
     * @throws FieldException
     */
    public function __unset($offset)
    {
        throw new FieldException('Records fields can not be unsetted');
    }

    /**
     * {@inheritdoc}
     */
    public function __isset($name)
    {
        if (isset($this->ormSchema[ORMInterface::M_RELATIONS][$name])) {
            return !empty($this->relation($name)->getRelated());
        }

        return parent::__isset($name);
    }

    /**
     * Direct access to relation by it's name.
     *
     * @see relation()
     *
     * @param string $method
     * @param array  $arguments
     *
     * @return RelationInterface|mixed|AccessorInterface
     */
    public function __call($method, array $arguments)
    {
        if (isset($this->ormSchema[ORMInterface::M_RELATIONS][$method])) {
            return $this->relation($method);
        }

        //See FIELD_FORMAT constant
        return parent::__call($method, $arguments);
    }

    /**
     * Get or create record relation by it's name and pre-loaded (optional) set of data.
     *
     * @param string $name
     *
     * @return RelationInterface
     *
     * @throws RelationException
     * @throws RecordException
     */
    public function relation($name)
    {
        if (array_key_exists($name, $this->relations)) {
            if ($this->relations[$name] instanceof RelationInterface) {
                return $this->relations[$name];
            }

            //Been preloaded
            return $this->initiateRelation($name, $this->relations[$name], true);
        }

        //Initiating empty relation object
        return $this->initiateRelation($name, null, false);
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

            foreach ($this->getFields(false) as $field => $value) {
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

        foreach ($this->getFields(false) as $value) {
            if ($value instanceof RecordAccessorInterface) {
                $value->flushUpdates();
            }
        }
    }

    /**
     * Create set of fields to be sent to UPDATE statement.
     *
     * @return array
     */
    public function compileUpdates()
    {
        if (!$this->hasUpdates() && !$this->isSolid()) {
            return [];
        }

        if ($this->isSolid()) {
            return $this->serializeData();
        }

        $updates = [];
        foreach ($this->getFields(false) as $field => $value) {
            if ($field == $this->ormSchema[ORMInterface::M_PRIMARY_KEY]) {
                continue;
            }

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

        return $updates;
    }

    /**
     * @return array
     */
    public function __debugInfo()
    {
        $info = [
            'table'  => $this->ormSchema[ORMInterface::M_DB] . '/' . $this->ormSchema[ORMInterface::M_TABLE],
            'fields' => $this->getFields(),
            'errors' => $this->getErrors(),
        ];

        return $info;
    }

    /**
     * {@inheritdoc}
     */
    public function isValid()
    {
        return parent::isValid() && empty($this->nestedErrors);
    }

    /**
     * {@inheritdoc}
     */
    public function getErrors($reset = false)
    {
        return parent::getErrors($reset) + $this->nestedErrors;
    }

    /**
     * Change record loaded state.
     *
     * @param bool|mixed $state
     *
     * @return $this
     */
    protected function loadedState($state)
    {
        $this->loaded = $state;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * Will validate every embedded relation.
     *
     * @param bool $reset
     *
     * @throws RecordException
     */
    protected function validate($reset = false)
    {
        $this->nestedErrors = [];

        foreach ($this->relations as $name => $relation) {
            if (!$relation instanceof ValidatesInterface) {
                //Never constructed
                continue;
            }

            if ($this->embeddedRelation($name) && !$relation->isValid()) {
                $this->nestedErrors[$name] = $relation->getErrors($reset);
            }
        }

        parent::validate($reset);

        return $this->hasErrors() && empty($this->nestedErrors);
    }

    /**
     * Get WHERE array to be used to perform record data update or deletion. Usually will include
     * record primary key.
     *
     * @return array
     */
    protected function stateCriteria()
    {
        if (!empty($primaryKey = $this->ormSchema[ORMInterface::M_PRIMARY_KEY])) {
            return [$primaryKey => $this->primaryKey()];
        }

        //We have to serialize record data
        return $this->updates + $this->serializeData();
    }

    /**
     * {@inheritdoc}
     */
    protected function container()
    {
        if (empty($this->orm) || !$this->orm instanceof Component) {
            return parent::container();
        }

        return $this->orm->container();
    }

    /**
     * Related and cached ORM schema.
     *
     * @internal
     *
     * @return array
     */
    protected function ormSchema()
    {
        return $this->ormSchema;
    }

    /**
     * Check if relation is embedded.
     *
     * @internal
     *
     * @param string $relation
     *
     * @return bool
     */
    protected function embeddedRelation($relation)
    {
        return !empty($this->ormSchema[ORMInterface::M_RELATIONS][$relation][ORMInterface::R_DEFINITION][self::EMBEDDED_RELATION]);
    }

    /**
     * @param array $data
     */
    private function extractRelations(array &$data)
    {
        $relations = array_intersect_key($data, $this->ormSchema[ORMInterface::M_RELATIONS]);

        foreach ($relations as $name => $relation) {
            $this->relations[$name] = $relation;
            unset($data[$name]);
        }
    }

    /**
     * @param string     $name
     * @param array|null $data
     * @param bool       $loaded
     * @return RelationInterface|void
     */
    private function initiateRelation($name, $data, $loaded)
    {
        if (!isset($this->ormSchema[ORMInterface::M_RELATIONS][$name])) {
            throw new RecordException(
                "Undefined relation {$name} in record " . static::class . '.'
            );
        }

        $relation = $this->ormSchema[ORMInterface::M_RELATIONS][$name];

        return $this->relations[$name] = $this->orm->relation(
            $relation[ORMInterface::R_TYPE],
            $this,
            $relation[ORMInterface::R_DEFINITION],
            $data,
            $loaded
        );
    }

    /**
     * {@inheritdoc}
     *
     * @see   Component::staticContainer()
     *
     * @param array $fields Record fields to set, will be passed thought filters.
     * @param ORM   $orm    ORM component, global container will be called if not instance provided.
     * @event created()
     */
    public static function create($fields = [], ORMInterface $orm = null)
    {
        /**
         * @var RecordEntity $record
         */
        $record = new static([], false, $orm);

        //Forcing validation (empty set of fields is not valid set of fields)
        $record->setFields($fields)->dispatch('created', new EntityEvent($record));

        return $record;
    }
}