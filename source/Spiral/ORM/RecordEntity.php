<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM;

use Spiral\Core\Component;
use Spiral\Core\Traits\SaturateTrait;
use Spiral\Models\AccessorInterface;
use Spiral\Models\SchematicEntity;
use Spiral\Models\Traits\SolidableTrait;
use Spiral\ORM\Commands\DeleteCommand;
use Spiral\ORM\Commands\InsertCommand;
use Spiral\ORM\Commands\NullCommand;
use Spiral\ORM\Commands\UpdateCommand;
use Spiral\ORM\Entities\RelationBucket;
use Spiral\ORM\Events\RecordEvent;
use Spiral\ORM\Exceptions\FieldException;
use Spiral\ORM\Exceptions\RecordException;
use Spiral\ORM\Exceptions\RelationException;

/**
 * Provides ActiveRecord-less abstraction for carried data with ability to automatically apply
 * setters, getters, generate update, insert and delete sequences and access nested relations.
 *
 * Class implementations statically analyzed to define DB schema.
 *
 * @see RecordEntity::SCHEMA
 *
 * Potentially requires split for StateWatcher.
 */
abstract class RecordEntity extends SchematicEntity implements RecordInterface
{
    use SaturateTrait, SolidableTrait;

    /*
     * Begin set of behaviour and description constants.
     * ================================================
     */

    /**
     * Set of schema sections needed to describe entity behaviour.
     */
    const SH_PRIMARIES = 0;
    const SH_DEFAULTS  = 1;
    const SH_RELATIONS = 6;

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

    /*
     * ================================================
     * End set of behaviour and description constants.
     */

    /**
     * Model behaviour configurations.
     */
    const SECURED   = '*';
    const HIDDEN    = [];
    const FILLABLE  = [];
    const SETTERS   = [];
    const GETTERS   = [];
    const ACCESSORS = [];

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
     * Record field updates (changed values). This array contain set of initial property values if
     * any of them changed.
     *
     * @var array
     */
    private $changes = [];

    /**
     * AssociatedRelation bucket. Manages declared record relations.
     *
     * @var RelationBucket
     */
    protected $relations;

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
     * @param array             $data
     * @param int               $state
     * @param ORMInterface|null $orm
     * @param array|null        $schema
     */
    public function __construct(
        array $data = [],
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

        $this->relations = new RelationBucket($this, $this->orm);
        $this->relations->extractRelations($data);

        parent::__construct($data + $this->recordSchema[self::SH_DEFAULTS], $this->recordSchema);
    }

    /**
     * Check if entity been loaded (non new).
     *
     * @return bool
     */
    public function isLoaded(): bool
    {
        return $this->state != ORMInterface::STATE_NEW;
    }

    /**
     * Current model state.
     *
     * @return int
     */
    public function getState(): int
    {
        return $this->state;
    }

    /**
     * {@inheritdoc}
     *
     * @throws RelationException
     */
    public function getField(string $name, $default = null, bool $filter = true)
    {
        if ($this->relations->has($name)) {
            return $this->relations->getRelated($name);
        }

        $this->assertField($name);

        return parent::getField($name, $default, $filter);
    }

    /**
     * {@inheritdoc}
     *
     * @param bool $registerChanges Track field changes.
     *
     * @throws RelationException
     */
    public function setField(
        string $name,
        $value,
        bool $filter = true,
        bool $registerChanges = true
    ) {
        if ($this->relations->has($name)) {
            //Would not work with relations which do not represent singular entities
            $this->relations->setRelated($name, $value);

            return;
        }

        $this->assertField($name);
        if ($registerChanges) {
            $this->registerChange($name);
        }

        parent::setField($name, $value, $filter);
    }

    /**
     * {@inheritdoc}
     */
    public function hasField(string $name): bool
    {
        if ($this->relations->has($name)) {
            return $this->relations->hasRelated($name);
        }

        return parent::hasField($name);
    }

    /**
     * {@inheritdoc}
     *
     * @throws FieldException
     * @throws RelationException
     */
    public function __unset($offset)
    {
        if ($this->relations->has($offset)) {
            //Flush associated relation value if possible
            $this->relations->flushRelated($offset);

            return;
        }

        if (!$this->isNullable($offset)) {
            throw new FieldException("Unable to unset not nullable field '{$offset}'");
        }

        $this->setField($offset, null, false);
    }

    /**
     * {@inheritdoc}
     *
     * Method does not check updates in nested relation, but only in primary record.
     *
     * @param string $field Check once specific field changes.
     */
    public function hasChanges(string $field = null): bool
    {
        //Check updates for specific field
        if (!empty($field)) {
            if (array_key_exists($field, $this->changes)) {
                return true;
            }

            //Do not force accessor creation
            $value = $this->getField($field, null, false);
            if ($value instanceof RecordAccessorInterface && $value->hasUpdates()) {
                return true;
            }

            return false;
        }

        if (!empty($this->changes)) {
            return true;
        }

        //Do not force accessor creation
        foreach ($this->getFields(false) as $value) {
            //Checking all fields for changes (handled internally)
            if ($value instanceof RecordAccessorInterface && $value->hasUpdates()) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     *
     * @param bool $queueRelations
     *
     * @throws RecordException
     * @throws RelationException
     */
    public function queueSave(bool $queueRelations = true): CommandInterface
    {
        if ($this->state == ORMInterface::STATE_READONLY) {
            //Nothing to do on readonly entities
            return new NullCommand();
        }

        if (!$this->isLoaded()) {
            $command = $this->prepareInsert();
        } else {
            if ($this->hasChanges() || $this->solidState) {
                $command = $this->prepareUpdate();

            } else {
                $command = new NullCommand();
            }
        }

        //Changes are flushed BEFORE entity is saved, this is required to present
        //recursive update loops
        $this->flushChanges();

        //Relation commands
        if ($queueRelations) {
            //Queue relations before and after parent command (if needed)
            return $this->relations->queueRelations($command);
        }

        return $command;
    }

    /**
     * {@inheritdoc}
     *
     * @throws RecordException
     * @throws RelationException
     */
    public function queueDelete(): CommandInterface
    {
        if ($this->state == ORMInterface::STATE_READONLY || !$this->isLoaded()) {
            //Nothing to do
            return new NullCommand();
        }

        return $this->prepareDelete();
    }

    /**
     * @return array
     */
    public function __debugInfo()
    {
        return [
            'database'  => $this->orm->define(static::class, ORMInterface::R_DATABASE),
            'table'     => $this->orm->define(static::class, ORMInterface::R_TABLE),
            'fields'    => $this->getFields(),
            'relations' => $this->relations
        ];
    }

    /**
     * {@inheritdoc}
     *
     * DocumentEntity will pass ODM instance as part of accessor context.
     *
     * @see CompositionDefinition
     */
    protected function createAccessor(
        $accessor,
        string $name,
        $value,
        array $context = []
    ): AccessorInterface {
        //Giving ORM as context
        return parent::createAccessor($accessor, $name, $value, $context + ['orm' => $this->orm]);
    }

    /**
     * {@inheritdoc}
     */
    protected function iocContainer()
    {
        if ($this->orm instanceof Component) {
            //Forwarding IoC scope to parent ORM instance
            return $this->orm->iocContainer();
        }

        return parent::iocContainer();
    }

    /*
     * Code below used to generate transaction commands.
     */

    /**
     * Change object state.
     *
     * @param int $state
     */
    private function setState(int $state)
    {
        $this->state = $state;
    }

    /**
     * @return InsertCommand
     */
    private function prepareInsert(): InsertCommand
    {
        //Entity indicates it's own status
        $this->setState(ORMInterface::STATE_SCHEDULED_INSERT);

        $command = new InsertCommand(
            $this->packValue(),
            $this->orm->define(static::class, ORMInterface::R_DATABASE),
            $this->orm->define(static::class, ORMInterface::R_TABLE)
        );

        $this->dispatch('insert', new RecordEvent($this, $command));

        //Executed when transaction successfully completed
        $command->onComplete(function ($command) {
            $this->setState(ORMInterface::STATE_LOADED);
            $this->dispatch('created', new RecordEvent($this));

            //Sync context?
        });

        return $command;
    }

    /**
     * @return UpdateCommand
     */
    private function prepareUpdate(): UpdateCommand
    {
        //Entity indicates it's own status
        $this->setState(ORMInterface::STATE_SCHEDULED_UPDATE);

        $command = new UpdateCommand(
            $this->stateCriteria(),
            $this->packChanges(true),
            $this->orm->define(static::class, ORMInterface::R_DATABASE),
            $this->orm->define(static::class, ORMInterface::R_TABLE)
        );

        $this->dispatch('update', new RecordEvent($this, $command));

        //Executed when transaction successfully completed
        $command->onComplete(function ($command) {
            $this->setState(ORMInterface::STATE_LOADED);
            $this->dispatch('updated', new RecordEvent($this, $command));

            //Sync context?
        });

        return $command;
    }

    /**
     * @return DeleteCommand
     */
    private function prepareDelete(): DeleteCommand
    {
        //Entity indicates it's own status
        $this->setState(ORMInterface::STATE_SCHEDULED_DELETE);
        $this->dispatch('delete', new RecordEvent($this));

        $command = new DeleteCommand(
            $this->stateCriteria(),
            $this->orm->define(static::class, ORMInterface::R_DATABASE),
            $this->orm->define(static::class, ORMInterface::R_TABLE)
        );

        //Executed when transaction successfully completed
        $command->onComplete(function () {
            $this->setState(ORMInterface::STATE_DELETED);
            $this->dispatch('deleted', new RecordEvent($this));
        });

        return $command;
    }

    /**
     * Get WHERE array to be used to perform record data update or deletion. Usually will include
     * record primary key.
     *
     * Usually just [ID => value] array.
     *
     * @return array
     */
    private function stateCriteria()
    {
        if (!empty($primaryKey = $this->recordSchema[self::SH_PRIMARIES])) {

            //Set of primary keys
            $state = [];
            foreach ($primaryKey as $key) {
                $state[$key] = $this->getField($key);
            }

            return $state;
        }

        //Use entity data as where definition
        return $this->changes + $this->packValue();
    }

    /**
     * Create set of fields to be sent to UPDATE statement.
     *
     * @param bool $skipPrimaries Remove primary keys from update statement.
     *
     * @return array
     */
    private function packChanges(bool $skipPrimaries = false): array
    {
        if (!$this->hasChanges() && !$this->isSolid()) {
            return [];
        }

        if ($this->isSolid()) {
            //Solid records always saved as one chunk of data
            return $this->packValue();
        }

        $updates = [];
        foreach ($this->getFields(false) as $field => $value) {
            if (
                $skipPrimaries
                && in_array($field, $this->recordSchema[self::SH_PRIMARIES])
            ) {
                continue;
            }

            //Handled by sub-accessor
            if ($value instanceof RecordAccessorInterface) {
                if ($value->hasUpdates()) {
                    $updates[$field] = $value->compileUpdates($field);
                    continue;
                }

                $value = $value->packValue();
            }

            //Field change registered
            if (array_key_exists($field, $this->changes)) {
                $updates[$field] = $value;
            }
        }

        return $updates;
    }

    /**
     * Indicate that all updates done, reset dirty state.
     */
    private function flushChanges()
    {
        $this->changes = [];

        foreach ($this->getFields(false) as $field => $value) {
            if ($value instanceof RecordAccessorInterface) {
                $value->flushUpdates();
            }
        }
    }

    /**
     * @param string $name
     */
    private function registerChange(string $name)
    {
        $original = $this->getField($name, null, false);

        if (!array_key_exists($name, $this->changes)) {
            //Let's keep track of how field looked before first change
            $this->changes[$name] = $original instanceof AccessorInterface
                ? $original->packValue()
                : $original;
        }
    }

    /**
     * @param string $name
     *
     * @throws FieldException
     */
    private function assertField(string $name)
    {
        if (!$this->hasField($name)) {
            throw new FieldException(sprintf(
                "No such property '%s' in '%s', check schema being relevant",
                $name,
                get_called_class()
            ));
        }
    }
}