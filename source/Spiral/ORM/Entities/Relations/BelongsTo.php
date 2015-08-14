<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM\Entities\Relations;

use Spiral\ORM\Exceptions\RelationException;
use Spiral\ORM\Model;

/**
 * Represents simple BELONGS_TO relation with ability to associate and de-associate parent.
 */
class BelongsTo extends HasOne
{
    /**
     * Relation type, required to fetch model class from relation definition.
     */
    const RELATION_TYPE = Model::BELONGS_TO;

    /**
     * {@inheritdoc}
     */
    public function isLoaded()
    {
        if (empty($this->parent->getField($this->definition[Model::INNER_KEY], false))) {
            return true;
        }

        return $this->loaded;
    }

    /**
     * {@inheritdoc}
     *
     * Parent model MUST be saved in order to preserve parent association.
     */
    public function associate($related = null)
    {
        if ($related === null) {
            $this->deassociate();

            return;
        }

        /**
         * @var Model $related
         */
        if (!$related->isLoaded()) {
            throw new RelationException(
                "Unable to set 'belongs to' parent, parent has be fetched from database."
            );
        }

        parent::associate($related);

        //Key in parent model
        $outerKey = $this->definition[Model::OUTER_KEY];

        //Key in child model
        $innerKey = $this->definition[Model::INNER_KEY];

        if ($this->parent->getField($innerKey, false) != $related->getField($outerKey, false)) {
            //We are going to set relation keys right on assertion
            $this->parent->setField($innerKey, $related->getField($outerKey, false), false);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function mountRelation(Model $model)
    {
        //Nothing to do, children can not update parent relation
        return $model;
    }

    /**
     * {@inheritdoc}
     *
     * @throws RelationException
     */
    protected function createSelector()
    {
        if (empty($this->parent->getField($this->definition[Model::INNER_KEY], false))) {
            throw new RelationException(
                "Belongs-to selector can not be constructed when inner key ("
                . $this->definition[Model::INNER_KEY]
                . ") is null."
            );
        }

        return parent::createSelector();
    }

    /**
     * De associate related model.
     */
    protected function deassociate()
    {
        if (!$this->definition[Model::NULLABLE]) {
            throw new RelationException(
                "Unable to de-associate relation data, relation is not nullable."
            );
        }

        $innerKey = $this->definition[Model::INNER_KEY];
        $this->parent->setField($innerKey, null, false);

        $this->loaded = true;
        $this->instance = null;
        $this->data = [];
    }
}