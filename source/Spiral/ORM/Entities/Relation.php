<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM\Entities;

use Spiral\ORM\Exceptions\RelationException;
use Spiral\ORM\Model;
use Spiral\ORM\ORM;
use Spiral\ORM\RelationInterface;

/**
 * Abstract implementation of ORM Relations, provides access to associated instances, use ORM entity
 * cache and model iterators. In additional can be serialized into json, or iterated when needed.
 */
abstract class Relation implements RelationInterface, \Countable, \IteratorAggregate, \JsonSerializable
{
    /**
     * Relation type, required to fetch model class from relation definition.
     */
    const RELATION_TYPE = null;

    /**
     * Indication that relation represent multiple models (HAS_MANY relations).
     */
    const MULTIPLE = false;

    /**
     * Indication that relation data has been loaded from databases.
     *
     * @var bool
     */
    protected $loaded = false;

    /**
     * Pre-loaded relation data, can be loaded while parent model, or later. Real data instance will
     * be constructed on demand and will keep it pre-loaded context between calls.
     *
     * @see Model::setContext()
     * @var array|null
     */
    protected $data = [];

    /**
     * Instance of constructed ActiveRecord of ModelIterator.
     *
     * @invisible
     * @var Model|ModelIterator
     */
    protected $instance = null;

    /**
     * Parent Model caused relation to be created.
     *
     * @var Model
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
     * @param ORM   $orm
     * @param Model $parent
     * @param array $definition Relation definition, must be normalized by relation schema.
     * @param mixed $data       Pre-loaded relation data.
     * @param bool  $loaded     Indication that relation data has been loaded.
     */
    public function __construct(
        ORM $orm,
        Model $parent,
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
     */
    public function getRelated()
    {
        if (!empty($this->instance)) {
            if ($this->instance instanceof Model && !empty($this->data)) {
                //We have to keep model relation context (pivot data and pre-loaded relations)
                $this->instance->setContext($this->data);
            }

            //ModelIterator will update context automatically
            return $this->instance;
        }

        if (!$this->isLoaded()) {
            //Loading data if not already loaded
            $this->loadData();
        }

        if (empty($this->data)) {
            //Can not be loaded, let's use empty iterator
            return static::MULTIPLE ? $this->createIterator() : null;
        }

        return $this->instance = (static::MULTIPLE ? $this->createIterator() : $this->createModel());
    }

    /**
     * {@inheritdoc}
     */
    public function associate($related)
    {
        if (static::MULTIPLE) {
            throw new RelationException(
                "Unable to associate relation data (relation represent multiple records)."
            );
        }

        if (!is_array($allowed = $this->getClass())) {
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
             * @var ModelIterator|Model[] $instance
             */
            foreach ($instance as $model) {
                if ($model->isDeleted()) {
                    continue;
                }

                //Forcing keys and etc
                if (!$this->mountRelation($model)->save($validate, true)) {
                    return false;
                }

                $this->orm->registerEntity($model);
            }

            return true;
        }

        /**
         * @var Model $instance
         */
        if ($instance->isDeleted()) {
            //Deleted by user
            return true;
        }

        if (!$this->mountRelation($instance)->save($validate, true)) {
            return false;
        }

        $this->orm->registerEntity($instance);

        return true;
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

        if (!$loaded || !($this->instance instanceof Model)) {
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
            if ($related instanceof Model) {
                return $related->isValid();
            }

            return true;
        }

        /**
         * @var ModelIterator|Model[] $data
         */
        $hasErrors = false;
        foreach ($related as $model) {
            if (!$model->isValid()) {
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
        $related = $this->getRelated();

        if (!static::MULTIPLE) {
            if ($related instanceof Model) {
                return $related->hasErrors();
            }

            return false;
        }

        /**
         * @var ModelIterator|Model[] $data
         */
        $hasErrors = false;
        foreach ($related as $model) {
            if (!$model->isValid()) {
                $hasErrors = true;
            }
        }

        return $hasErrors;
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
            if ($related instanceof Model) {
                return $related->getErrors($reset);
            }

            return [];
        }

        /**
         * @var ModelIterator|Model[] $data
         */
        $errors = [];
        foreach ($related as $position => $model) {
            if (!$model->isValid()) {
                $errors[$position] = $model->getErrors($reset);
            }
        }

        return !empty($errors);
    }

    /**
     * {@inheritdoc}
     *
     * Count of PRE-LOADED models. Use relation selector to perform query to database.
     *
     * @return int
     */
    public function count()
    {
        if (!$this->isLoaded()) {
            $this->loadData();
        }

        return count($this->data);
    }

    /**
     * Perform iterator on pre-loaded data. Use relation selector to iterate thought custom relation
     * query.
     *
     * @return Model[]|ModelIterator
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
     *
     * @return Selector
     */
    public function __invoke(array $arguments)
    {
        return $this->createSelector()->where($arguments);
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return $this->getRelated();
    }

    /**
     * Class name of outer model.
     *
     * @return string
     */
    protected function getClass()
    {
        return $this->definition[static::RELATION_TYPE];
    }

    /**
     * Mount relation keys to parent or children models to ensure their connection. Method called
     * when model requests relation save.
     *
     * @param Model $model
     * @return Model
     */
    abstract protected function mountRelation(Model $model);

    /**
     * Convert pre-loaded relation data to model iterator model.
     *
     * @return ModelIterator
     */
    protected function createIterator()
    {
        return new ModelIterator($this->orm, $this->getClass(), $this->data);
    }

    /**
     * Convert pre-loaded relation data to active record model.
     *
     * @return Model
     */
    protected function createModel()
    {
        return $this->orm->model($this->getClass(), $this->data);
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
     * Must be redeclared in child implementations.
     *
     * @return Selector
     */
    protected function createSelector()
    {
        return new Selector($this->orm, $this->getClass());
    }
}