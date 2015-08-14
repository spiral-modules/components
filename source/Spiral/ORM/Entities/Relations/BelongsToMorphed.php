<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM\Entities\Relations;

use Spiral\ORM\Entities\Selector;
use Spiral\ORM\Exceptions\RelationException;
use Spiral\ORM\Model;

/**
 * Similar to BelongsTo, however morph key value used to resolve parent model.
 */
class BelongsToMorphed extends BelongsTo
{
    /**
     * Relation type, required to fetch model class from relation definition.
     */
    const RELATION_TYPE = Model::BELONGS_TO_MORPHED;

    /**
     * {@inheritdoc}
     */
    public function associate($related = null)
    {
        parent::associate($related);
        $morphKey = $this->definition[Model::MORPH_KEY];
        if (is_null($related)) {
            $this->parent->setField($morphKey, null);

            return;
        }

        /**
         * @var Model $related
         */
        $this->parent->setField($morphKey, $related->getRole(), false);
    }

    /**
     * {@inheritdoc}
     */
    protected function createSelector()
    {
        //To prevent morph key being added as where
        $selector = new Selector($this->orm, $this->getClass());

        return $selector->where(
            $selector->getPrimaryAlias() . '.' . $this->definition[Model::OUTER_KEY],
            $this->parent->getField($this->definition[Model::INNER_KEY], false)
        );
    }

    /**
     * {@inheritdoc}
     *
     * @throws RelationException
     */
    protected function getClass()
    {
        $morphKey = $this->definition[Model::MORPH_KEY];
        if (empty($this->parent->getField($morphKey))) {
            throw new RelationException("Unable to resolve parent entity, morph key is empty.");
        }

        return parent::getClass()[$this->parent->getField($morphKey)];
    }

    /**
     * {@inheritdoc}
     */
    protected function deassociate()
    {
        parent::deassociate();

        //Dropping morph key value
        $this->parent->setField($this->definition[Model::MORPH_KEY], null);
    }
}