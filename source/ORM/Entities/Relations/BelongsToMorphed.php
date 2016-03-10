<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\ORM\Entities\Relations;

use Spiral\Models\EntityInterface;
use Spiral\ORM\Entities\RecordSelector;
use Spiral\ORM\Exceptions\RelationException;
use Spiral\ORM\RecordEntity;

/**
 * Similar to BelongsTo, however morph key value used to resolve parent record.
 */
class BelongsToMorphed extends BelongsTo
{
    /**
     * Relation type, required to fetch record class from relation definition.
     */
    const RELATION_TYPE = RecordEntity::BELONGS_TO_MORPHED;

    /**
     * {@inheritdoc}
     */
    public function associate(EntityInterface $related = null)
    {
        parent::associate($related);
        $morphKey = $this->definition[RecordEntity::MORPH_KEY];

        if (is_null($related)) {
            $this->parent->setField($morphKey, null);

            return;
        }

        /*
         * @var RecordEntity $related
         */
        $this->parent->setField($morphKey, $related->recordRole());
    }

    /**
     * {@inheritdoc}
     */
    protected function createSelector()
    {
        //To prevent morph key being added as where
        $selector = new RecordSelector($this->orm, $this->getClass());

        return $selector->where(
            $selector->primaryAlias() . '.' . $this->definition[RecordEntity::OUTER_KEY],
            $this->parent->getField($this->definition[RecordEntity::INNER_KEY], false)
        );
    }

    /**
     * {@inheritdoc}
     *
     * @throws RelationException
     */
    protected function getClass()
    {
        $morphKey = $this->definition[RecordEntity::MORPH_KEY];
        if (empty($this->parent->getField($morphKey))) {
            throw new RelationException('Unable to resolve parent entity, morph key is empty.');
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
        $this->parent->setField($this->definition[RecordEntity::MORPH_KEY], null);
    }
}
