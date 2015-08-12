<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM;

use Spiral\Core\ContainerInterface;
use Spiral\Database\Entities\Table;
use Spiral\Database\Exceptions\QueryException;
use Spiral\Models\AccessorInterface;
use Spiral\Models\ActiveEntityInterface;
use Spiral\Models\DataEntity;
use Spiral\ODM\CompositableInterface;
use Spiral\ORM\Exceptions\ModelException;
use Spiral\ORM\Exceptions\ORMException;
use Spiral\ORM\Exceptions\RelationException;

/**
 * Document is base data model for ORM component, it used to describe related table schema, filters,
 * validations and relations to other models. You can count Model class as ActiveRecord pattern.
 * ORM component will automatically analyze existed Documents and create cached version of their
 * schema.
 */
class Model extends DataEntity implements ActiveEntityInterface
{
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
     * ORM models are be divided by two sections: active and passive models. When model is active
     * ORM allowed to modify associated model table using declared schema and created relations.
     *
     * Passive models (ACTIVE_SCHEMA = false) however can only read table schema from database and
     * forbidden to do any schema modification either by model or by relations.
     *
     * You can use ACTIVE_SCHEMA = false in cases where you need to create an ActiveRecord for
     * existed table.
     *
     * @see ModelSchema
     * @see \Spiral\ORM\Entities\SchemaBuilder
     */
    const ACTIVE_SCHEMA = true;

    /**
     * Indication that model were deleted.
     */
    const DELETED = 900;

    /**
     * Default ORM relation types, see ORM configuration and documentation for more information,
     * i had to remove 200 lines of comments to make model little bit smaller.
     *
     * @see RelationSchemaInterface
     * @see RelationSchema
     */
    const HAS_ONE      = 101;
    const HAS_MANY     = 102;
    const BELONGS_TO   = 103;
    const MANY_TO_MANY = 104;

    /**
     * Morphed relation types are usually created by inversion or equivalent of primary relation types.
     *
     * @see RelationSchemaInterface
     * @see RelationSchema
     * @see MorphedRelation
     */
    const BELONGS_TO_MORPHED = 108;
    const MANY_TO_MORPHED    = 109;

    /**
     * Constants used to declare relations in model schema, used in normalized relation schema.
     *
     * @see RelationSchemaInterface
     */
    const OUTER_KEY         = 901; //Outer key name
    const INNER_KEY         = 902; //Inner key name
    const MORPH_KEY         = 903; //Morph key name
    const PIVOT_TABLE       = 904; //Pivot table name
    const PIVOT_COLUMNS     = 905; //Pre-defined pivot table columns
    const THOUGHT_INNER_KEY = 906; //Pivot table options
    const THOUGHT_OUTER_KEY = 907; //Pivot table options
    const WHERE             = 908; //Where conditions
    const WHERE_PIVOT       = 909; //Where pivot conditions

    /**
     * Additional constants used to control relation schema behaviour.
     *
     * @see Model::$schema
     * @see RelationSchemaInterface
     */
    const INVERSE           = 1001; //Relation should be inverted to parent model
    const CONSTRAINT        = 1002; //Relation should create foreign keys (default)
    const CONSTRAINT_ACTION = 1003; //Default relation foreign key delete/update action (CASCADE)
    const CREATE_PIVOT      = 1004; //Many-to-Many should create pivot table automatically (default)
    const NULLABLE          = 1005; //Relation can be nullable (default)
    const CREATE_INDEXES    = 1006; //Indication that relation is allowed to create required indexes
    const MORPHED_ALIASES   = 1007; //Aliases for morphed sub-relations

    /**
     * Constants used to declare indexes in model schema.
     *
     * @see Model::$indexes
     */
    const INDEX  = 1000;            //Default index type
    const UNIQUE = 2000;            //Unique index definition

    /**
     * Indicates that model data were loaded from database (not recently created).
     *
     * @var bool
     */
    private $loaded = false;

    /**
     * SolidState will force model data to be saved as one big update set without any generating
     * separate update statements for changed columns.
     *
     * @var bool
     */
    private $solidState = false;

    /**
     * Populated when model loaded using many-to-many connection. Property will include every
     * column of connection row in pivot table.
     *
     * @see setContext()
     * @see getPivot();
     * @var array
     */
    private $pivotData = [];

    /**
     * Table name (without database prefix) model associated to, ModelSchema will generate table
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
     * Set of indexes to be created for associated model table, indexes only created when model is
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
     * Model relations and columns can be described in one place - model schema.
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
     * Every created column will be stated as NOT NULL with forced default value, if you want to have
     * nullable columns, specify special data key:
     * protected $schema = [
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
     *          self::HAS_ONE => 'Models\Profile',
     *          self::INVERSE => 'user'
     *      ],
     *      'roles'       => [
     *          self::MANY_TO_MANY => 'Models\Role',
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
     * Model field updates (changed values).
     *
     * @var array
     */
    protected $updates = [];

    /**
     * Constructed and pre-cached set of model relations. Relation will be in a form of data array
     * to be created on demand.
     *
     * @see relation()
     * @see __call()
     * @see __set()
     * @see __get()
     * @var RelationInterface[]|array
     */
    protected $relations = [];

    /**
     * @invisible
     * @var ORM
     */
    protected $orm = null;

    /**
     * @param array      $data
     * @param bool|false $loaded
     * @param ORM|null   $orm
     * @param array      $schema
     */
    public function __construct(
        array $data = [],
        $loaded = false,
        ORM $orm = null,
        array $schema = []
    ) {
        $this->loaded = $loaded;
        $this->orm = !empty($orm) ? $orm : self::container()->get(ORM::class);
        $this->schema = !empty($schema) ? $schema : $orm->getSchema(static::class);

        static::initialize();

        if (isset($data[ORM::PIVOT_DATA])) {
            $this->pivotData = $data[ORM::PIVOT_DATA];
            unset($data[ORM::PIVOT_DATA]);
        }

        foreach (array_intersect_key($data, $this->schema[ORM::M_RELATIONS]) as $name => $relation)
        {
            $this->relations[$name] = $relation;
            unset($data[$name]);
        }

        //Merging with default values
        $this->fields = $data + $this->schema[ORM::M_COLUMNS];

        if (!$this->isLoaded()) {
            //Non loaded models should be in solid state by default and require initial validation
            $this->solidState(true)->validated = true;
        }
    }

    /**
     * Model context must be updated in cases where single model instance can be accessed from multiple
     * places, context will not change model fields but might overwrite pivot data or clarify
     * loaded relations.
     *
     * @param array $context
     * @return $this
     */
    public function setContext(array $context)
    {
        //Mounting context pivot data
        $this->pivotData = isset($context[ORM::PIVOT_DATA]) ? $context[ORM::PIVOT_DATA] : [];

        $relations = array_intersect_key($context, $this->schema[ORM::M_RELATIONS]);
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
         * We are not going to update model fields.
         */

        return $this;
    }

    /**
     * Change model solid state. SolidState will force model data to be saved as one big update set
     * without any generating separate update statements for changed columns.
     *
     * Attention, you have to carefully use forceUpdate flag with models without primary keys due
     * update criteria (WHERE condition) can not be easy constructed for models with primary key.
     *
     * @param bool $solidState
     * @param bool $forceUpdate Mark all fields as changed to force update later.
     * @return $this
     */
    public function solidState($solidState, $forceUpdate = false)
    {
        $this->solidState = $solidState;

        if ($forceUpdate) {
            if ($this->schema[ORM::M_PRIMARY_KEY]) {
                $this->updates = $this->getCriteria();
            } else {
                $this->updates = $this->schema[ORM::M_COLUMNS];
            }
        }

        return $this;
    }

    /**
     * Is model is solid state?
     *
     * @see solidState()
     * @return bool
     */
    public function isSolid()
    {
        return $this->solidState;
    }

    /**
     * Role name used in morphed relations to detect outer model table and class. It usually built
     * based on model class name, but can be defined using ROLE_NAME constant.
     *
     * @return string
     */
    public function getRole()
    {
        return $this->schema[ORM::M_ROLE_NAME];
    }

    /**
     * Model primary key value (if any).
     *
     * @return mixed
     */
    public function primaryKey()
    {
        return isset($this->fields[$this->schema[ORM::M_PRIMARY_KEY]])
            ? $this->fields[$this->schema[ORM::M_PRIMARY_KEY]]
            : null;
    }

    /**
     * Is model were fetched from databases or recently created?
     *
     * @return bool
     */
    public function isLoaded()
    {
        return (bool)$this->loaded && !$this->isDeleted();
    }

    /**
     * Indication that model data was deleted.
     *
     * @return bool
     */
    public function isDeleted()
    {
        return $this->loaded === self::DELETED;
    }

    /**
     * Pivot data associated with model instance, populated only in cases when model loaded using
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
     * Must track field updates. In addition Models will not allow to set unknown field.
     *
     * @throws ModelException
     */
    public function setField($name, $value, $filter = true)
    {
        if (!array_key_exists($name, $this->fields)) {
            throw new ModelException("Undefined field '{$name}' in '" . static::class . "'.");
        }

        $original = isset($this->fields[$name]) ? $this->fields[$name] : null;
        if ($value === null && in_array($name, $this->schema[ORM::M_NULLABLE])) {
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
     * Get or create model relation by it's name and pre-loaded (optional) set of data.
     *
     * @param string $name
     * @param mixed  $data
     * @param bool   $loaded
     * @return RelationInterface
     * @throws RelationException
     * @throws ModelException
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
        if (!isset($this->schema[ORM::M_RELATIONS][$name])) {
            throw new ModelException(
                "Undefined relation {$name} in model " . static::class . "."
            );
        }

        $relation = $this->schema[ORM::M_RELATIONS][$name];

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
     */
    public function publicFields()
    {
        $fields = $this->getFields();
        foreach ($this->schema[ORM::M_HIDDEN] as $secured) {
            unset($fields[$secured]);
        }

        return $this->fire('publicFields', $fields);
    }

    /**
     * {@inheritdoc}
     */
    public function validator(array $rules = [], ContainerInterface $container = null)
    {
        //Initiate validation using rules declared in schema
        return parent::validator(
            !empty($rules) ? $rules : $this->schema[ORM::M_VALIDATES],
            $container
        );
    }

    /**
     *
     * {@inheritdoc}
     *
     * Create or update model data in database.
     *
     * @see   sourceTable()
     * @see   getCriteria()
     * @param bool|null $validate  Overwrite default option declared in VALIDATE_SAVE to force or
     *                             disable validation before saving.
     * @param bool      $relations Save data associated to constructed model relations.
     * @return bool
     * @throws ModelException
     * @throws QueryException
     * @event saving()
     * @event saved()
     * @event updating()
     * @event updated()
     */
    public function save($validate = null, $relations = true)
    {
        if (is_null($validate)) {
            $validate = static::VALIDATE_SAVE;
        }

        if ($validate && !$this->isValid()) {
            return false;
        }

        if ($validate && $relations) {
            //We have to validate relations before saving them
            foreach ($this->relations as $name => $relation) {
                if (!$relation instanceof RelationInterface) {
                    //Was never constructed
                    continue;
                }

                if (!$relation->isValid()) {
                    $this->setError($name, $relation->getErrors());
                }
            }

            if (!empty($this->errors)) {
                //Some relations are invalid and can not be saved
                return false;
            }
        }

        //Primary key field name
        $primaryKey = $this->schema[ORM::M_PRIMARY_KEY];
        if (!$this->isLoaded()) {
            $this->fire('saving');

            //We will need to support models with multiple primary keys in future
            unset($this->fields[$primaryKey]);

            //Creating
            $lastID = $this->sourceTable()->insert($this->fields = $this->serializeData());
            if (!empty($primaryKey)) {
                //Updating model primary key
                $this->fields[$primaryKey] = $lastID;
            }

            $this->loaded = true;
            $this->fire('saved');

            //Saving model to entity cache if we have space for that
            $this->orm->registerEntity($this, false);
        } elseif ($this->solidState || $this->hasUpdates()) {
            $this->fire('updating');

            //Updating
            $this->sourceTable()->update($this->compileUpdates(), $this->getCriteria())->run();

            $this->fire('updated');
        }

        $this->flushUpdates();

        if ($relations) {
            foreach ($this->relations as $name => $relation) {
                if (!$relation instanceof RelationInterface) {
                    //Was never constructed
                    continue;
                }

                if (!$relation->saveAssociation($validate)) {
                    throw new ModelException("Unable to save relation '{$name}'.");
                }
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

        $this->fields = $this->schema[ORM::M_COLUMNS];
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
                if ($value instanceof ModelAccessorInterface && $value->hasUpdates()) {
                    return true;
                }
            }

            return false;
        }

        if (array_key_exists($field, $this->updates)) {
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
            if ($value instanceof ModelAccessorInterface) {
                $value->flushUpdates();
            }
        }
    }

    /**
     * {@inheritdoc}
     *
     * @throws ModelException
     */
    public function __unset($offset)
    {
        throw new ModelException("Models fields can not be unsetted.");
    }

    /**
     * {@inheritdoc}
     *
     * @see relation()
     */
    public function __get($offset)
    {
        if (isset($this->schema[ORM::M_RELATIONS][$offset])) {
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
        if (isset($this->schema[ORM::M_RELATIONS][$offset])) {
            //Bypassing call to relation
            $this->relation($offset)->associate($value);

            return;
        }

        $this->setField($offset, $value, true);
    }

    /**
     * Direct access to relation by it's name. No __invoke method of relation will be called if no
     * arguments were provided, which makes __call synonym of relation() method.
     *
     * @see relation()
     * @param string $method
     * @param array  $arguments
     * @return RelationInterface
     */
    public function __call($method, array $arguments)
    {
        if (isset($this->schema[ORM::M_RELATIONS][$method])) {
            $relation = $this->relation($method);

            return empty($arguments) ? $relation : call_user_func_array($relation, $arguments);
        }

        return parent::__call($method, $arguments);
    }

    /**
     * @return Object
     */
    public function __debugInfo()
    {
        $info = [
            'table'     => $this->schema[ORM::M_DB] . '/' . $this->schema[ORM::M_TABLE],
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

        $updates = [];
        foreach ($this->fields as $name => $field) {
            if ($field instanceof ModelAccessorInterface && ($this->isSolid() || $field->hasUpdates())) {
                //Update handled by accessor
                $updates[$name] = $field->compileUpdates($name);
                continue;
            }

            if (!$this->isSolid() && !array_key_exists($name, $this->updates)) {
                //No field updates
                continue;
            }

            if ($field instanceof ModelAccessorInterface) {
                $field = $field->serializeData();
            }

            $updates[$name] = $field;
        }

        //Primary key should not present in update set
        unset($updates[$this->schema[ORM::M_PRIMARY_KEY]]);

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
        return $this->orm->dbalDatabase(
            $this->schema[ORM::M_DB]
        )->table(
            $this->schema[ORM::M_TABLE]
        );
    }

    /**
     * Get WHERE array to be used to perform model data update or deletion. Usually will include
     * model primary key.
     *
     * @return array
     */
    protected function getCriteria()
    {
        if (!empty($this->schema[ORM::M_PRIMARY_KEY])) {
            return [$this->schema[ORM::M_PRIMARY_KEY] => $this->primaryKey()];
        }

        //We have to serialize model data
        return $this->updates + $this->serializeData();
    }

    /**
     * {@inheritdoc}
     *
     * Will validate every CompositableInterface instance. Used for JsonDocuments.
     */
    protected function validate()
    {
        $errors = [];
        //Validating all compositions
        foreach ($this->fields as $field => $value) {
            if (!$value instanceof CompositableInterface) {
                //Something weird.
                continue;
            }

            if (!$value->isValid()) {
                $errors[$field] = $value->getErrors();
            }
        }

        parent::validate();
        $this->errors = $this->errors + $errors;

        return empty($this->errors);
    }

    /**
     * {@inheritdoc}
     */
    protected function isFillable($field)
    {
        if (!empty($this->schema[ORM::M_FILLABLE])) {
            return in_array($field, $this->schema[ORM::M_FILLABLE]);
        }

        if ($this->schema[ORM::M_SECURED] === '*') {
            return false;
        }

        return !in_array($field, $this->schema[ORM::M_SECURED]);
    }

    /**
     * {@inheritdoc}
     */
    protected function getMutator($field, $mutator)
    {
        if (isset($this->schema[ORM::M_MUTATORS][$mutator][$field])) {
            return $this->schema[ORM::M_MUTATORS][$mutator][$field];
        }

        return null;
    }

    /**
     * {@inheritdoc}
     *
     * @param array $fields Model fields to set, will be passed thought filters.
     * @param ORM   $orm    ORM component, global container will be called if not instance provided.
     * @event created()
     */
    public static function create($fields = [], ORM $orm = null)
    {
        //Only when global container is set
        $orm = !empty($orm) ? $orm : self::container()->get(ORM::class);

        /**
         * @var Model $model
         */
        $model = new static([], false, $orm);

        //Forcing validation (empty set of fields is not valid set of fields)
        $model->validated = false;
        $model->setFields($fields)->fire('created');

        return $model;
    }

    /**
     * Find multiple models based on provided query.
     *
     * Example:
     * User::find(['status' => 'active'], ['profile']);
     *
     * @param array $where Selection WHERE statement.
     * @param array $load  Array or relations to be pre-loaded.
     * @return Selector|Model[]
     */
    public static function find(array $where = [], array $load = [])
    {
        return static::ormSelector()->load($load)->find($where);
    }

    /**
     * Fetch one model based on provided query or return null. Use second argument to specify
     * relations to be loaded.
     *
     * Example:
     * User::findOne(['name' => 'Wolfy-J'], ['profile'], ['id' => 'DESC']);
     *
     * @param array $where   Selection WHERE statement.
     * @param array $load    Array or relations to be pre-loaded.
     * @param array $orderBy Sort by conditions.
     * @return Model|null
     */
    public static function findOne(array $where = [], array $load = [], array $orderBy = [])
    {
        $selector = static::find($where, $load);
        foreach ($orderBy as $column => $direction) {
            $selector->orderBy($column, $direction);
        }

        return $selector->findOne();
    }

    /**
     * Find model using it's primary key. Relation data can be preloaded with found model.
     *
     * Example:
     * User::findByID(1, ['profile']);
     *
     * @param mixed $id   Primary key.
     * @param array $load Array or relations to be pre-loaded.
     * @return Model|null
     */
    public static function findByPK($id = null, array $load = [])
    {
        return static::ormSelector()->load($load)->findByPK($id);
    }

    /**
     * Instance of ORM Selector associated with specific document.
     *
     * @param ORM $orm ORM component, global container will be called if not instance provided.
     * @return Selector
     * @throws ORMException
     * @event selector(Selector $collection)
     */
    protected static function ormSelector(ORM $orm = null)
    {
        //Only when global container is set
        $orm = !empty($orm) ? $orm : self::container()->get(ORM::class);

        //Ensure traits
        static::initialize();

        return static::events()->fire('selector', new Selector(static::class, $orm));
    }
}