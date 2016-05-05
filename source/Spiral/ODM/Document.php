<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\ODM;

use Spiral\Models\ActiveEntityInterface;
use Spiral\Models\EntityInterface;
use Spiral\Models\Events\EntityEvent;
use Spiral\ODM\Exceptions\DocumentException;
use Spiral\ODM\Traits\FindTrait;

/**
 * DocumentEntity with added ActiveRecord methods and ability to connect to associated source.
 *
 * Document also provides an ability to specify aggregations using it's schema:
 *
 * protected $schema = [
 *     ...,
 *     'outer' => [
 *          self::ONE => Outer::class, [ //Reference to outer document using internal
 *              '_id' => 'self::outerID' //outerID value
 *          ]
 *      ],
 *     'many' => [
 *          self::MANY => Outer::class, [ //Reference to many outer document using
 *              'innerID' => 'self::_id'  //document primary key
 *          ]
 *     ]
 * ];
 *
 * Note: self::{name} construction will be replaced with document value in resulted query, even
 * in case of arrays ;) You can also use dot notation to get value from nested document.
 *
 * Attention, document will be linked to default database and named collection by default, use
 * properties database and collection to define your own custom database and collection.
 *
 * You can use property "index" to declare needed document indexes:
 *
 * Set of indexes to be created for associated collection. Use self::INDEX_OPTIONS or "@options"
 * for additional parameters.
 *
 * Example:
 * protected $indexes = [
 *      ['email' => 1, '@options' => ['unique' => true]],
 *      ['name' => 1]
 * ];
 *
 * @link http://php.net/manual/en/mongocollection.ensureindex.php
 *
 * Configuration properties:
 * - database
 * - collection
 * - schema
 * - indexes
 * - defaults
 * - secured (* by default)
 * - fillable
 * - validate
 */
abstract class Document extends DocumentEntity implements ActiveEntityInterface
{
    use FindTrait;

    /**
     * Indication that save methods must be validated by default, can be altered by calling save
     * method with user arguments.
     */
    const VALIDATE_SAVE = true;

    /**
     * @see Component::staticContainer()
     *
     * @param array           $fields
     * @param EntityInterface $parent
     * @param ODMInterface    $odm
     * @param array           $odmSchema
     */
    public function __construct(
        $fields = [],
        EntityInterface $parent = null,
        ODMInterface $odm = null,
        $odmSchema = null
    ) {
        parent::__construct($fields, $parent, $odm, $odmSchema);

        if ((!$this->isLoaded() && !$this->isEmbedded())) {
            //Document is newly created instance
            $this->solidState(true)->invalidate();
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return \MongoId|null
     */
    public function primaryKey()
    {
        return $this->getField('_id', null, false);
    }

    /**
     * {@inheritdoc}
     */
    public function isLoaded()
    {
        return (bool)$this->primaryKey();
    }

    /**
     * {@inheritdoc}
     *
     * Create or update document data in database.
     *
     * @param bool|null $validate Overwrite default option declared in VALIDATE_SAVE to force or
     *                            disable validation before saving.
     *
     * @throws DocumentException
     * @event saving()
     * @event saved()
     * @event updating()
     * @event updated()
     */
    public function save($validate = null)
    {
        $validate = !is_null($validate) ? $validate : static::VALIDATE_SAVE;

        if ($validate && !$this->isValid()) {
            //Using default model behaviour
            return false;
        }

        if ($this->isEmbedded()) {
            throw new DocumentException(
                "Embedded document '" . get_class($this) . "' can not be saved into collection"
            );
        }

        //Associated collection
        $mapper = $this->odm->mapper(static::class);

        if (!$this->isLoaded()) {
            $this->dispatch('saving', new EntityEvent($this));

            /*
             * Performing an insertion using ODM class mapper.
             */
            $mongoID = $mapper->insert($this->serializeData());
            $this->setField('_id', $mongoID);

            $this->dispatch('saved', new EntityEvent($this));

        } elseif ($this->isSolid() || $this->hasUpdates()) {

            /*
             * Performing an update using ODM class mapper.
             */
            $this->dispatch('updating', new EntityEvent($this));
            $mapper->update($this->primaryKey(), $this->buildAtomics());
            $this->dispatch('updated', new EntityEvent($this));
        }

        $this->flushUpdates();

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @throws DocumentException
     * @event deleting()
     * @event deleted()
     */
    public function delete()
    {
        if ($this->isEmbedded()) {
            throw new DocumentException(
                "Embedded document '" . get_class($this) . "' can not be deleted from collection"
            );
        }

        $this->dispatch('deleting', new EntityEvent($this));

        /*
         * Performing deletion using ODM class mapper.
         */
        if ($this->isLoaded()) {
            $this->odm->mapper(static::class)->delete($this->primaryKey());
            $this->setField('_id', null, false);
        }

        $this->dispatch('deleted', new EntityEvent($this));
    }
}