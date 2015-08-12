<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM;

use Spiral\Database\Database;
use Spiral\Database\Table;
use Spiral\Models\AccessorInterface;
use Spiral\Models\DataEntity;

abstract class Model extends DataEntity
{
    /**
     * We are going to inherit parent validation, we have to let i18n indexer know to collect both
     * local and parent messages under one bundle.
     */
    const I18N_INHERIT_MESSAGES = true;

    /**
     * Set this constant to false to disable automatic column, index and foreign keys creation.
     *
     * By default entities will read schema from database, so you can connect your ORM model to
     * already existed table.
     *
     * Attention, orm update will fail if any external model requested changed in table linked to
     * ActiveRecord with ACTIVE_SCHEMA = false.
     *
     * ATTENTION SOMETHING IMPORTABT!
     */
    const ACTIVE_SCHEMA = true;

    /**
     * Model specific constant to indicate that model has to be validated while saving. You still can
     * change this behaviour manually by providing argument to save method.
     */
    const FORCE_VALIDATION = true;

    /**
     * Indication that model data was deleted.
     */
    const DELETED = 900;

    /**
     * Relation types, read documentation and examples to get more information.
     */
    const HAS_ONE      = 101;
    const HAS_MANY     = 102;
    const BELONGS_TO   = 103;
    const MANY_TO_MANY = 104;

    /**
     * This is internal relation types, in most of cases relation like that will be created
     * automatically when relation detect that target is interface and not real class.
     */
    const BELONGS_TO_MORPHED = 108;
    const MANY_TO_MORPHED    = 109;

    /**
     * Constants used to declare relation schemas.
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
     * Additional constants used to control relation schema creation.
     */
    const INVERSE           = 1001; //Relation should be inverted to parent model
    const CONSTRAINT        = 1002; //Relation should create foreign keys (default)
    const CONSTRAINT_ACTION = 1003; //Default relation foreign key delete/update action (CASCADE)
    const CREATE_PIVOT      = 1004; //Many-to-Many should create pivot table automatically (default)
    const NULLABLE          = 1005; //Relation can be nullable (default)
    const CREATE_INDEXES    = 1006; //Indication that relation is allowed to create required indexes
    const MORPHED_ALIASES   = 1007; //Aliases for morphed sub-relations

    /**
     * Constants used to declare index type. See documentation for indexes property.
     */
    const INDEX  = 1000;            //Default index type
    const UNIQUE = 2000;            //Unique index definition

    /**
     * Already fetched schemas from ORM. Yes, ORM ActiveRecord is really similar to ODM. Original ORM
     * was written long time ago before ODM and solutions i put to ORM was later used for ODM, while
     * "great transition" (tm) ODM was significantly updated and now ODM drive updates for ORM,
     * the student become the teacher.
     *
     * @var array
     */
    private static $schemaCache = [];

    /**
     * ORM component.
     *
     * @var ORM
     */
    protected $orm = null;

    /**
     * Table associated with ActiveRecord. Spiral will guess table name automatically based on class
     * name use Doctrine Inflector, however i'm STRONGLY recommend to declare table name manually as
     * it gives more readable code.
     *
     * @var string
     */
    protected $table = null;

    /**
     * Database name/id where record table located in. By default database will be used if nothing
     * else is specified.
     *
     * @var string
     */
    protected $database = 'default';

    /**
     * Indication that model data was successfully fetched from database.
     *
     * @var bool
     */
    protected $loaded = false;

    /**
     * Populated when model loaded using many-to-many connection.
     *
     * @see getPivot();
     * @var array
     */
    protected $pivotData = [];

    /**
     * Record relations and columns. ActiveRecord schema can be used for multiple purposes - as for
     * describing model relations in a format listed above, as for declaring related table structure.
     *
     * While defining table structure make sure that ACTIVE_SCHEMA constant is set to true. Every
     * column declaration are similar to dbal migrations declaration.
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
     *      'status'  => 'enum(active,hidden)', //Enum values
     *      'balance' => 'decimal(10, 2)'       //Decimal size and precision
     * ];
     *
     * Every created column will be stated as NOT NULL with forced default value, if you want to have
     * nullable columns, specify special data key:
     * protected $schema = [
     *      'name'      => 'string,nullable'
     * ];
     *
     * You can easily combine table and relations definition:
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
     * Set of indexes to be created for associated model table, indexes will be created only if
     * model has enabled ACTIVE_SCHEMA constant.
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
     * Default values associated with record fields. This default values will be combined with values
     * fetched from table schema.
     *
     * @var array
     */
    protected $defaults = [];

    /**
     * ActiveRecord marked with solid state flag will be saved entirely without generating simplified
     * update operations with only changed fields.
     *
     * @var bool
     */
    protected $solidState = false;

    /**
     * List of updated fields associated with their original values.
     *
     * @var array
     */
    protected $updates = [];

    /**
     * Constructed and pre-cached set of relations.
     *
     * @var RelationInterface[]
     */
    protected $relations = [];

    /**
     * New instance of ActiveRecord.
     *
     * @param array $data
     * @param bool  $loaded
     * @param ORM   $orm
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
     * Get model schema.
     *
     * @return array
     */
    public function ormSchema()
    {
        return $this->schema;
    }

    /**
     * Role name used in morphed relations to detect outer model table and class.
     *
     * @return string
     */
    public function getRoleName()
    {
        return $this->schema[ORM::M_ROLE_NAME];
    }

    /**
     * SetContext method used by iterators and relations to update record context, this is required
     * due entity cache and ability of one record to be presented in multiple spots.
     *
     * @param array $data
     * @return $this
     */
    public function setContext(array $data)
    {
        //Mounting context pivot data
        $this->pivotData = isset($data[ORM::PIVOT_DATA]) ? $data[ORM::PIVOT_DATA] : [];

        foreach (array_intersect_key($data, $this->schema[ORM::M_RELATIONS]) as $name => $relation)
        {
            if (!isset($this->relations[$name]) || is_array($this->relations[$name])) {
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
     * Change record solid state flag value. Record marked with solid state flag will be saved
     * entirely without generating simplified update operations with only changed fields.
     *
     * Attention, you have to carefully use forceUpdate flag with models without primary keys.
     *
     * @param bool $solidState  Solid state flag value.
     * @param bool $forceUpdate Mark all fields as changed to force update later.
     * @return $this
     * @throws ORMException
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
     * Get document primary key (_id) value. This value can be used to identify if model loaded from
     * databases or just created.
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
     * Indication that model was deleted.
     *
     * @return bool
     */
    public function isDeleted()
    {
        return $this->loaded === self::DELETED;
    }

    /**
     * Get relation pivot data, only populated when model loaded under many-to-many relation.
     *
     * @return array
     */
    public function getPivot()
    {
        return $this->pivotData;
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
     */
    protected function isFillable($field)
    {
        //Better replace it with isset later
        return !in_array($field, $this->schema[ORM::M_SECURED])
        && !(
            $this->schema[ORM::M_FILLABLE]
            && !in_array($field, $this->schema[ORM::M_FILLABLE])
        );
    }

    /**
     * Get or create model relation by it's name and pre-loaded (optional) set of data.
     *
     * @param string $name
     * @param mixed  $data
     * @param bool   $loaded
     * @return RelationInterface
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

            return $this->relations[$name];
        }

        //Constructing relation
        if (!isset($this->schema[ORM::M_RELATIONS][$name])) {
            throw new ORMException("Undefined relation {$name} in model " . static::class . ".");
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
    public function __get($offset)
    {
        if (isset($this->schema[ORM::M_RELATIONS][$offset])) {
            return $this->relation($offset)->getAssociated();
        }

        return $this->getField($offset, true);
    }

    /**
     * {@inheritdoc}
     */
    public function setField($name, $value, $filter = true)
    {
        if (!array_key_exists($name, $this->fields)) {
            throw new ORMException("Undefined field '{$name}' in '" . static::class . "'.");
        }

        $original = $this->fields[$name];
        parent::setField($name, $value, $filter);

        if (!array_key_exists($name, $this->updates)) {
            $this->updates[$name] = $original instanceof AccessorInterface
                ? $original->serializeData()
                : $original;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function __set($offset, $value)
    {
        if (isset($this->schema[ORM::M_RELATIONS][$offset])) {
            $this->relation($offset)->associate($value);

            return;
        }

        $this->setField($offset, $value, true);
    }

    /**
     * Direct access to relation by it's name.
     *
     * @param string $method
     * @param array  $arguments
     * @return RelationInterface
     */
    public function __call($method, array $arguments)
    {
        $relation = $this->relation($method);

        return empty($arguments) ? $relation : call_user_func_array($relation, $arguments);
    }

    /**
     * Check if entity or specific field is updated.
     *
     * @param string $field
     * @return bool
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
     * Mark object as successfully updated and flush all existed atomic operations and updates.
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
     * Get array of changed or created fields for specified ActiveRecord or accessor.
     *
     * @return array
     */
    protected function compileUpdates()
    {
        if (!$this->hasUpdates() && !$this->solidState) {
            return [];
        }

        $updates = [];
        foreach ($this->fields as $name => $field) {
            if ($field instanceof ModelAccessorInterface && ($this->solidState || $field->hasUpdates())) {
                $updates[$name] = $field->compileUpdate($name);
                continue;
            }

            if (!$this->solidState && !array_key_exists($name, $this->updates)) {
                continue;
            }

            if ($field instanceof ModelAccessorInterface) {
                $field = $field->serializeData();
            }

            $updates[$name] = $field;
        }

        //Primary key should present in update set
        unset($updates[$this->schema[ORM::M_PRIMARY_KEY]]);

        return $updates;
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
    public function validator(array $validates = [])
    {
        if (!empty($this->validator)) {
            !empty($validates) && $this->validator->setRules($validates);

            //Refreshing data
            return $this->validator->setData($this->fields);
        }

        return parent::validator(!empty($validates) ? $validates : $this->schema[ORM::M_VALIDATES]);
    }

    /**
     * Get instance of DBAL\Table associated with specified record.
     *
     * @param ORM      $orm      ORM component, will be received from container if not provided.
     * @param Database $database Database instance, will be received from container if not provided.
     * @return Table
     */
    public static function dbalTable(ORM $orm = null, Database $database = null)
    {
        //Will work only when global container is set!
        $orm = !empty($orm) ? $orm : self::container()->get(ORM::class);

        $schema = $orm->getSchema(static::class);

        //We can bypass dbalDatabase() method here.
        $database = !empty($database) ? $database : $orm->getDatabase($schema[ORM::M_DB]);

        return $database->table($schema[ORM::M_TABLE]);
    }

    /**
     * Get instance of DBAL\Database associated with specified model.
     *
     * @param ORM $orm ORM component, will be received from container if not provided.
     * @return Database
     */
    public static function dbalDatabase(ORM $orm = null)
    {
        //Will work only when global container is set!
        $orm = !empty($orm) ? $orm : self::container()->get(ORM::class);

        return $orm->getDatabase($orm->getSchema(static::class)[ORM::M_DB]);
    }

    /**
     * Get associated orm Selector. Selectors used to build complex related queries and fetch
     * models from database. Method will not work without specifying ORM and without global container.
     *
     * @param ORM $orm ORM component, will be received from container if not provided.
     * @return Selector
     */
    public static function ormSelector(ORM $orm = null)
    {
        if (empty($odm)) {
            //Will work only when global container is set!
            $orm = ORM::instance(self::container());
        }

        //I must add selector scopes in future
        return static::events()->fire('selector', new Selector(static::class, $orm));
    }

    /**
     * Save record fields to associated table. Model has to be valid to be saved, in
     * other scenario method will return false, model errors can be found in getErrors() method.
     *
     * Events: saving, saved, updating, updated will be fired.
     *
     * @param bool $validate  Validate record fields before saving, enabled by default. Turning this
     *                        option off will increase performance but will make saving less secure.
     *                        You can use it when model data was not modified directly by user. By
     *                        default value is null which will force document to select behaviour
     *                        from FORCE_VALIDATION constant.
     * @param bool $relations Save all nested relations with valid foreign keys and etc, attention,
     *                        only pre-loaded or create relations will be saved for performance
     *                        reasons, no "MANY" relations will be saved. Enabled by default.
     * @return bool
     * @throws ORMException
     */
    public function save($validate = null, $relations = true)
    {
        if (is_null($validate)) {
            $validate = static::FORCE_VALIDATION;
        }

        if ($validate && !$this->isValid()) {
            return false;
        }

        //Primary key field name
        $primaryKey = $this->schema[ORM::M_PRIMARY_KEY];
        if (!$this->isLoaded()) {
            $this->fire('saving');

            //We will need to support models with primary keys in future
            unset($this->fields[$primaryKey]);

            $lastID = static::dbalTable($this->orm)->insert(
                $this->fields = $this->serializeData()
            );

            if (!empty($primaryKey)) {
                $this->fields[$primaryKey] = $lastID;
            }

            $this->loaded = true;
            $this->fire('saved');

            $this->orm->registerEntity($this);
        } elseif ($this->solidState || $this->hasUpdates()) {
            $this->fire('updating');

            static::dbalTable($this->orm)->update(
                $this->compileUpdates(),
                $this->getCriteria()
            )->run();

            $this->fire('updated');
        }

        $this->flushUpdates();

        if ($relations && !empty($this->relations)) {
            //We would like to save all relations under one transaction, so we can easily revert them
            //all, in future it will be reasonable to save primary model and relations under one
            //transaction
            $this->orm->getDatabase($this->schema[ORM::M_DB])->transaction(function () use (
                $validate
            ) {
                foreach ($this->relations as $name => $relation) {
                    if ($relation instanceof RelationInterface && !$relation->saveAssociation($validate)) {
                        //Let's record error
                        $this->setError($name, $relation->getErrors());

                        throw new ORMException("Unable to save relation.");
                    }
                }
            });
        }

        return true;
    }

    /**
     * Delete record from database. Attention, if your model does not have primary key result of
     * this method can be pretty dramatic as it will remove every record from associated table with
     * same set of field.
     *
     * Events: deleting, deleted will be raised.
     */
    public function delete()
    {
        $this->fire('deleting');

        if ($this->isLoaded()) {
            static::dbalTable($this->orm)->delete($this->getCriteria())->run();
        }

        $this->fields = $this->schema[ORM::M_COLUMNS];
        $this->loaded = self::DELETED;

        //TODO: remove from entity cache

        $this->fire('deleted');
    }

    /**
     * Get where condition to fetch current model from database, in cases where primary key is not
     * provided full model data will be used as where condition.
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
     * Create new model and set it's fields, all field values will be passed thought model filters
     * to ensure their type. Events: created
     *
     * You have to save model by yourself!
     *
     * @param array $fields Model fields to set, will be passed thought filters.
     * @return Model
     */
    public static function create($fields = [])
    {
        /**
         * @var Model $class
         */
        $class = new static();

        //Forcing validation (empty set of fields is not valid set of fields)
        $class->validated = true;
        $class->setFields($fields)->fire('created');

        return $class;
    }

    /**
     * Get ORM selector used to build complex SQL queries to fetch model and it's relations. Use
     * second argument to specify relations to be loaded.
     *
     * Example:
     * User::find(['status' => 'active'], ['profile']);
     *
     * @param array $where Selection WHERE statement.
     * @param array $load  Array or relations to be loaded.
     * @return Selector|Model[]
     */
    public static function find(array $where = [], array $load = [])
    {
        return static::ormSelector()->load($load)->find($where);
    }

    /**
     * Alias for find method. Get ORM selector used to build complex SQL queries to fetch model and
     * it's relations. Use second argument to specify relations to be loaded.
     *
     * Example:
     * User::select(['status' => 'active'], ['profile']);
     *
     * @param array $where Selection WHERE statement.
     * @param array $load  Array or relations to be loaded.
     * @return Selector|Model[]
     */
    public static function select(array $where = [], array $load = [])
    {
        return static::find($where, $load);
    }

    /**
     * Fetch one record from database or return null. Use second argument to specify relations to be
     * loaded.
     *
     * Example:
     * User::findOne(['name' => 'Wolfy-J'], ['profile'], ['id' => 'DESC']);
     *
     * @param array $where   Selection WHERE statement.
     * @param array $load    Array or relations to be loaded. You can't use INLOAD or JOIN_ONLY methods
     *                       with findOne.
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
     * Fetch one record from database by primary key value.
     *
     * Example:
     * User::findByID(1, ['profile']);
     *
     * @param mixed $id      Primary key.
     * @param array $load    Array or relations to be loaded. You can't use INLOAD or JOIN_ONLY methods
     *                       with findOne.
     * @return Model|null
     */
    public static function findByPK($id = null, array $load = [])
    {
        return static::ormSelector()->load($load)->findByPK($id);
    }

    /**
     * Simplified way to dump information.
     *
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
}