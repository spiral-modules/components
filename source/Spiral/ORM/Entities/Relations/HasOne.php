<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM\Entities\Relations;

use Spiral\ORM\Entities\Relation;
use Spiral\ORM\Exceptions\RelationException;
use Spiral\ORM\Model;

/**
 * Represent simple HAS_ONE relation with ability to associate and de-associate models.
 */
class HasOne extends Relation
{
    /**
     * Relation type, required to fetch model class from relation definition.
     */
    const RELATION_TYPE = Model::HAS_ONE;

    /**
     * {@inheritdoc}
     *
     * Attention, while associating old instance will not be removed or de-associated automatically!
     */
    public function associate($related = null)
    {
        //Removing association
        if (static::MULTIPLE == false && $related === null) {
            if (!$this->definition[Model::NULLABLE]) {
                throw new RelationException(
                    "Unable to de-associate relation data, relation is not nullable."
                );
            }

            $related = $this->getRelated();
            if ($related instanceof Model) {
                $related->setField($this->definition[Model::OUTER_KEY], null, false);
                if (isset($this->definition[Model::MORPH_KEY])) {
                    //Dropping morph key value
                    $related->setField($this->definition[Model::MORPH_KEY], null);
                }

                if (!$related->save()) {
                    throw new RelationException(
                        "Unable to de-associate existed and already related model, unable to save."
                    );
                }
            }

            $this->loaded = true;
            $this->instance = null;
            $this->data = [];

            return;
        }

        parent::associate($related);
        $this->mountRelation($related);
    }

    /**
     * Create model and configure it's fields with relation data. Attention, you have to validate and
     * save record by your own. Newly created entity will not be associated automatically!
     * Pre-loaded data will not be altered, unless reset() method are called.
     *
     * @param mixed $fields
     * @return Model
     */
    public function create($fields = [])
    {
        $model = call_user_func([$this->getClass(), 'create'], $fields, $this->orm);

        return $this->mountRelation($model);
    }

    /**
     * {@inheritdoc}
     */
    protected function mountRelation(Model $model)
    {
        //Key in child model
        $outerKey = $this->definition[Model::OUTER_KEY];

        //Key in parent model
        $innerKey = $this->definition[Model::INNER_KEY];

        if ($model->getField($outerKey, false) != $this->parent->getField($innerKey, false)) {
            $model->setField($outerKey, $this->parent->getField($innerKey, false), false);
        }

        if (!isset($this->definition[Model::MORPH_KEY])) {
            //No morph key presented
            return $model;
        }

        $morphKey = $this->definition[Model::MORPH_KEY];

        if ($model->getField($morphKey) != $this->parent->getRole()) {
            $model->setField($morphKey, $this->parent->getRole());
        }

        return $model;
    }

    /**
     * {@inheritdoc}
     */
    protected function createSelector()
    {
        $selector = parent::createSelector();

        //We are going to clarify selector manually (without loaders), that's easy relation

        if (isset($this->definition[Model::MORPH_KEY])) {
            $selector->where(
                $selector->getPrimaryAlias() . '.' . $this->definition[Model::MORPH_KEY],
                $this->parent->getRole()
            );
        }

        $selector->where(
            $selector->getPrimaryAlias() . '.' . $this->definition[Model::OUTER_KEY],
            $this->parent->getField($this->definition[Model::INNER_KEY], false)
        );

        return $selector;
    }
}