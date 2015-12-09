<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\ORM\Entities;

use Spiral\Core\Component;
use Spiral\Models\ActiveEntityInterface;
use Spiral\Models\EntityInterface;
use Spiral\Models\IdentifiedInterface;
use Spiral\ORM\Entities\Traits\AliasTrait;
use Spiral\ORM\Exceptions\RelationException;
use Spiral\ORM\ORM;
use Spiral\ORM\RecordEntity;
use Spiral\ORM\RecordInterface;
use Spiral\ORM\RelationInterface;

/**
 * Abstract implementation of ORM Relations, provides access to associated instances, use ORM entity
 * cache and record iterators. In additional, relation can be serialized into json, or iterated when
 * needed.
 *
 * This abstract implement built to work with ORM Record classes.
 */
abstract class Relation extends Component implements
    RelationInterface,
    \Countable,
    \IteratorAggregate,
    \JsonSerializable
{
    /**
     * {@} table aliases.
     */
    use AliasTrait;

    /**
     * Relation type, required to fetch record class from relation definition.
     */
    const RELATION_TYPE = null;

    /**
     * Indication that relation represent multiple records (HAS_MANY relations).
     */
    const MULTIPLE = false;

    /**
     * Indication that relation data has been loaded from databases.
     *
     * @var bool
     */
    protected $loaded = false;

    /**
     * Pre-loaded relation data, can be loaded while parent record, or later. Real data instance
     * will be constructed on demand and will keep it pre-loaded context between calls.
     *
     * @see Record::setContext()
     * @var array|null
     */
    protected $data = [];

    /**
     * Instance of constructed EntityInterface of RecordIterator.
     *
     * @invisible
     * @var EntityInterface|RecordIterator
     */
    protected $instance = null;

    /**
     * Parent Record caused relation to be created.
     *
     * @var RecordInterface
     */
    protected $parent = null;

    /**
     * Relation definition fetched from ORM schema. Must already be normalized by RelationSchema.
     *
     * @invisible
     * @var array
     */
    protected $definition = [];

    /**
     * @invisible
     * @var ORM
     */
    protected $orm = null;

    /**
     * @param ORM             $orm
     * @param RecordInterface $parent
     * @param array           $definition Relation definition, must be normalized by relation
     *                                    schema.
     * @param mixed           $data       Pre-loaded relation data.
     * @param bool            $loaded     Indication that relation data has been loaded.
     */
    public function __construct(
        ORM $orm,
        RecordInterface $parent,
        array $definition,
        $data = null,
        $loaded = false
    ) {
        $this->orm = $orm;
        $this->parent = $parent;
        $this->definition = $definition;
        $this->data = $data;
        $this->loaded = $loaded;
    }

    /**
     * {@inheritdoc}
     */
    public function isLoaded()
    {
        return $this->loaded;
    }

    /**
     * {@inheritdoc}
     *
     * Relation will automatically create related record if relation is not nullable. Usually
     * applied for has one relations ($user->profile).
     */
    public function getRelated()
    {
        if (!empty($this->instance)) {
            if ($this->instance instanceof RecordInterface && !empty($this->data)) {
                //We have to keep record relation context (pivot data and pre-loaded relations)
                $this->instance->setContext($this->data);
            }

            //RecordIterator will update context automatically
            return $this->instance;
        }

        if (!$this->isLoaded()) {
            //Loading data if not already loaded
            $this->loadData();
        }

        if (empty($this->data)) {
            if (
                array_key_exists(RecordEntity::NULLABLE, $this->definition)
                && !$this->definition[RecordEntity::NULLABLE]
                && !static::MULTIPLE
            ) {
                //Not nullable relations must always return requested instance
                return $this->instance = $this->emptyRecord();
            }

            //Can not be loaded, let's use empty iterator
            return static::MULTIPLE ? $this->createIterator() : null;
        }

        return $this->instance = (static::MULTIPLE ? $this->createIterator() : $this->createRecord());
    }

    /**
     * {@inheritdoc}
     */
    public function associate(EntityInterface $related = null)
    {
        if (static::MULTIPLE) {
            throw new RelationException(
                "Unable to associate relation data (relation represent multiple records)."
            );
        }

        //Simplification for morphed relations
        if (!is_array($allowed = $this->definition[static::RELATION_TYPE])) {
            $allowed = [$allowed];
        }

        if (!is_object($related) || !in_array(get_class($related), $allowed)) {
            $allowed = join("', '", $allowed);

            throw new RelationException(
                "Only instances of '{$allowed}' can be assigned to this relation."
            );
        }

        //Entity caching
        $this->instance = $related;
        $this->loaded = true;
        $this->data = [];
    }

    /**
     * {@inheritdoc}
     */
    public function saveAssociation($validate = true)
    {
        if (empty($instance = $this->getRelated())) {
            //Nothing to save
            return true;
        }

        if (static::MULTIPLE) {
            /**
             * @var RecordIterator|EntityInterface[] $instance
             */
            foreach ($instance as $record) {
                if (!$this->saveEntity($record, $validate)) {
                    return false;
                }
            }

            return true;
        }

        return $this->saveEntity($instance, $validate);
    }

    /**
     * {@inheritdoc}
     */
    public function reset(array $data = [], $loaded = false)
    {
        if ($loaded && !empty($this->data) && $this->data == $data) {
            //Nothing to do, context is the same
            return;
        }

        if (!$loaded || !($this->instance instanceof EntityInterface)) {
            //Flushing instance
            $this->instance = null;
        }

        $this->data = $data;
        $this->loaded = $loaded;
    }

    /**
     * {@inheritdoc}
     */
    public function isValid()
    {
        $related = $this->getRelated();
        if (!static::MULTIPLE) {
            if ($related instanceof EntityInterface) {
                return $related->isValid();
            }

            return true;
        }

        /**
         * @var RecordIterator|EntityInterface[] $data
         */
        $hasErrors = false;
        foreach ($related as $entity) {
            if (!$entity->isValid()) {
                $hasErrors = true;
            }
        }

        return !$hasErrors;
    }

    /**
     * {@inheritdoc}
     */
    public function hasErrors()
    {
        return !$this->isValid();
    }

    /**
     * List of errors associated with parent field, every field must have only one error assigned.
     *
     * @param bool $reset Clean errors after receiving every message.
     * @return array
     */
    public function getErrors($reset = false)
    {
        $related = $this->getRelated();

        if (!static::MULTIPLE) {
            if ($related instanceof EntityInterface) {
                return $related->getErrors($reset);
            }

            return [];
        }

        /**
         * @var RecordIterator|EntityInterface[] $data
         */
        $errors = [];
        foreach ($related as $position => $record) {
            if (!$record->isValid()) {
                $errors[$position] = $record->getErrors($reset);
            }
        }

        return !empty($errors);
    }

    /**
     * Get selector associated with relation.
     *
     * @param array $where
     * @return RecordSelector
     */
    public function find(array $where = [])
    {
        return $this->createSelector()->where($where);
    }

    /**
     * {@inheritdoc}
     *
     * Use getRelation() method to count pre-loaded data.
     *
     * @return int
     */
    public function count()
    {
        return $this->createSelector()->count();
    }

    /**
     * Perform iterator on pre-loaded data. Use relation selector to iterate thought custom relation
     * query.
     *
     * @return RecordEntity|RecordEntity[]|RecordIterator
     */
    public function getIterator()
    {
        return $this->getRelated();
    }

    /**
     * Bypassing call to created selector.
     *
     * @param string $method
     * @param array  $arguments
     * @return mixed
     */
    public function __call($method, array $arguments)
    {
        return call_user_func_array([$this->createSelector(), $method], $arguments);
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return $this->getRelated();
    }

    /**
     * {@inheritdoc}
     */
    protected function container()
    {
        return $this->orm->container();
    }

    /**
     * Class name of outer record.
     *
     * @return string
     */
    protected function getClass()
    {
        return $this->definition[static::RELATION_TYPE];
    }

    /**
     * Mount relation keys to parent or children records to ensure their connection. Method called
     * when record requests relation save.
     *
     * @param EntityInterface $record
     * @return EntityInterface
     */
    abstract protected function mountRelation(EntityInterface $record);

    /**
     * Convert pre-loaded relation data to record iterator record.
     *
     * @return RecordIterator
     */
    protected function createIterator()
    {
        return new RecordIterator($this->orm, $this->getClass(), $this->data);
    }

    /**
     * Convert pre-loaded relation data to active record record.
     *
     * @return RecordEntity
     */
    protected function createRecord()
    {
        return $this->orm->record($this->getClass(), $this->data);
    }

    /**
     * Create empty record to be associated with non nullable relation.
     *
     * @return RecordEntity
     */
    protected function emptyRecord()
    {
        $record = $this->orm->record($this->getClass(), []);
        $this->associate($record);

        return $record;
    }

    /**
     * Load relation data based on created selector.
     *
     * @return array|null
     */
    protected function loadData()
    {
        if (!$this->parent->isLoaded()) {
            //Nothing to load for unloaded parents
            return null;
        }

        $this->loaded = true;
        if (static::MULTIPLE) {
            return $this->data = $this->createSelector()->fetchData();
        }

        $data = $this->createSelector()->fetchData();
        if (isset($data[0])) {
            return $this->data = $data[0];
        }

        return null;
    }

    /**
     * Internal ORM relation method used to create valid selector used to pre-load relation data or
     * create custom query based on relation options.
     *
     * Must be redeclarated in child implementations.
     *
     * @return RecordSelector
     */
    protected function createSelector()
    {
        return $this->orm->selector($this->getClass());
    }

    /**
     * Save simple related entity.
     *
     * @param EntityInterface $entity
     * @param bool            $validate
     * @return bool|void
     */
    private function saveEntity(EntityInterface $entity, $validate)
    {
        if ($entity instanceof RecordInterface && $entity->isDeleted()) {
            return true;
        }

        if (!$entity instanceof ActiveEntityInterface) {
            throw new RelationException("Unable to save non active entity.");
        }

        $this->mountRelation($entity);
        if (!$entity->save($validate)) {
            return false;
        }

        if ($entity instanceof IdentifiedInterface) {
            $this->orm->cache()->rememberEntity($entity);
        }

        return true;
    }
}
