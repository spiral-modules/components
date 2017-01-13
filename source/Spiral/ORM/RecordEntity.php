<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM;

use Spiral\Core\Traits\SaturateTrait;
use Spiral\Models\Traits\SolidableTrait;
use Spiral\ORM\Commands\DeleteCommand;
use Spiral\ORM\Commands\InsertCommand;
use Spiral\ORM\Commands\NullCommand;
use Spiral\ORM\Commands\UpdateCommand;
use Spiral\ORM\Entities\RelationBucket;
use Spiral\ORM\Events\RecordEvent;
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
abstract class RecordEntity extends AbstractRecord implements RecordInterface
{
    use SaturateTrait, SolidableTrait;

    /*
     * Begin set of behaviour and description constants.
     * ================================================
     */

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
     * Record state.
     *
     * @var int
     */
    private $state;

    /**
     * Points to last queued insert command for this entity, required to properly handle multiple
     * entity updates inside one transaction.
     *
     * @var InsertCommand
     */
    private $insertCommand = null;

    /**
     * Initiate entity inside or outside of ORM scope using given fields and state.
     *
     * @param array             $data
     * @param int               $state
     * @param ORMInterface|null $orm
     * @param array|null        $recordSchema
     */
    public function __construct(
        array $data = [],
        int $state = ORMInterface::STATE_NEW,
        ORMInterface $orm = null,
        array $recordSchema = null
    ) {
        //We can use global container as fallback if no default values were provided
        $orm = $this->saturate($orm, ORMInterface::class);

        $this->state = $state;
        if ($this->state == ORMInterface::STATE_NEW) {
            //Non loaded records should be in solid state by default
            $this->solidState(true);
        }

        parent::__construct($orm, $data, new RelationBucket($this, $orm));
    }

    /**
     * Check if entity been loaded (non new).
     *
     * @return bool
     */
    public function isLoaded(): bool
    {
        return $this->state != ORMInterface::STATE_NEW
            && !empty($this->getField($this->primaryColumn(), null, false));
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
     * @param bool $queueRelations
     *
     * @throws RecordException
     * @throws RelationException
     */
    public function queueStore(bool $queueRelations = true): CommandInterface
    {
        if ($this->state == ORMInterface::STATE_READONLY) {
            //Nothing to do on readonly entities
            return new NullCommand();
        }

        if ($this->state & ORMInterface::STATE_SCHEDULED) {
            throw new RecordException(
                "Unable to save already scheduled record, commit previous transaction first"
            );
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

        if ($this->state & ORMInterface::STATE_SCHEDULED) {
            throw new RecordException(
                "Unable to delete scheduled record, commit previous transaction first"
            );
        }

        return $this->prepareDelete();
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
        $command = new InsertCommand($this->packValue(), $this->orm->table(static::class));

        //Entity indicates it's own status
        $this->setState(ORMInterface::STATE_SCHEDULED_INSERT);
        $this->dispatch('insert', new RecordEvent($this, $command));

        //Executed when transaction successfully completed
        $command->onComplete($this->syncState());

        //Keep reference to the last insert command
        return $this->insertCommand = $command;
    }

    /**
     * @return UpdateCommand
     */
    private function prepareUpdate(): UpdateCommand
    {
        $command = new UpdateCommand(
            $this->packChanges(true),
            $this->isLoaded() ? $this->primaryKey() : null,
            $this->orm->table(static::class)
        );

        if (!empty($this->insertCommand)) {
            $this->insertCommand->onExecute(function (InsertCommand $insert) use ($command) {
                //Sync primary key values
                $command->setPrimary($insert->lastInsertID());
            });
        }

        //Entity indicates it's own status
        $this->setState(ORMInterface::STATE_SCHEDULED_UPDATE);
        $this->dispatch('update', new RecordEvent($this));

        //Executed when transaction successfully completed
        $command->onComplete($this->syncState());

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
            $this->primaryKey(),
            $this->orm->define(static::class, ORMInterface::R_DATABASE),
            $this->orm->define(static::class, ORMInterface::R_TABLE)
        );

        //Executed when transaction successfully completed
        $command->onComplete($this->syncState());

        return $command;
    }

    private function syncState(): \Closure
    {
        return function ($command) {
            dump($command);
        };

        //got command!

//        //Command context MIGHT include some fields set by parent commands (i.e. runtime values)
//        foreach ($command->getContext() as $field => $value) {
//            $this->setField($field, $value, true, false);
//        }
//
//        $this->setField(
//            $this->recordSchema[self::SH_PRIMARY_KEY],
//            $command->primaryKey(),
//            true,
//            false
//        );

    }
}