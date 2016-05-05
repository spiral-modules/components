<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\ORM;

use Spiral\Database\Exceptions\QueryException;
use Spiral\Models\ActiveEntityInterface;
use Spiral\Models\Events\EntityEvent;
use Spiral\ORM\Exceptions\RecordException;
use Spiral\ORM\Traits\FindTrait;

/**
 * Entity with ability to be saved and direct access to source. Record behaviour are defined in
 * protected or private properties such as schema, defaults and etc.
 *
 * Example:
 *
 * class User extends Record
 * {
 *      protected $schema = [
 *          'id'        => 'primary',
 *          'name'      => 'string',
 *          'biography' => 'text'
 *      ];
 * }
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
 *
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
 * Configuration properties:
 * - schema
 * - defaults
 * - secured (* by default)
 * - fillable
 * - validates
 * - database
 * - table
 * - indexes
 */
class Record extends RecordEntity implements ActiveEntityInterface
{
    use FindTrait;

    /**
     * Indication that save methods must be validated by default, can be altered by calling save
     * method with user arguments.
     */
    const VALIDATE_SAVE = true;

    /**
     * {@inheritdoc}
     *
     * Create or update record data in database. Record will validate all EMBEDDED and loaded
     * relations.
     *
     * @param bool|null $validate  Overwrite default option declared in VALIDATE_SAVE to force or
     *                             disable validation before saving.
     * @return bool
     *
     * @throws RecordException
     * @throws QueryException
     *
     * @event saving()
     * @event saved()
     * @event updating()
     * @event updated()
     */
    public function save($validate = null)
    {
        if (is_null($validate)) {
            //Using default model behaviour
            $validate = static::VALIDATE_SAVE;
        }

        if ($validate && !$this->isValid()) {
            return false;
        }

        //Associated mapper
        $mapper = $this->orm->mapper(static::class);

        if (!$this->isLoaded()) {
            $this->dispatch('saving', new EntityEvent($this));

            //Primary key field name (if any)
            $primaryKey = $this->ormSchema()[ORMInterface::M_PRIMARY_KEY];

            //Inserting
            $lastID = $mapper->insert($this->serializeData());

            if (!empty($primaryKey)) {
                //Updating record primary key
                $this->setField($primaryKey, $lastID);
            }

            $this->loadedState(true)->dispatch('saved', new EntityEvent($this));

        } elseif ($this->isSolid() || $this->hasUpdates()) {
            $this->dispatch('updating', new EntityEvent($this));

            //Performing update using associated mapper
            $mapper->update($this->stateCriteria(), $this->compileUpdates());

            $this->dispatch('updated', new EntityEvent($this));
        }

        $this->flushUpdates();
        $this->saveRelations($validate);

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
        $this->dispatch('deleting', new EntityEvent($this));

        if ($this->isLoaded()) {
            $this->orm->mapper(static::class)->delete($this->stateCriteria());
        }

        $this->loadedState(self::DELETED)->dispatch('deleted', new EntityEvent($this));
    }

    /**
     * Save embedded relations.
     *
     * @param bool $validate
     */
    protected function saveRelations($validate)
    {
        foreach ($this->relations as $name => $relation) {
            if (!$relation instanceof RelationInterface) {
                //Not constructed
                continue;
            }

            if ($this->embeddedRelation($name) && !$relation->saveRelated($validate)) {
                throw new RecordException("Unable to save relation '{$name}'");
            }
        }
    }
}