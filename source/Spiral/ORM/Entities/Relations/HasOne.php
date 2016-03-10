<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\ORM\Entities\Relations;

use Spiral\Models\EntityInterface;
use Spiral\ORM\Entities\Relation;
use Spiral\ORM\Exceptions\RelationException;
use Spiral\ORM\RecordEntity;

/**
 * Represent simple HAS_ONE relation with ability to associate and de-associate records.
 */
class HasOne extends Relation
{
    /**
     * Relation type, required to fetch record class from relation definition.
     */
    const RELATION_TYPE = RecordEntity::HAS_ONE;

    /**
     * {@inheritdoc}
     *
     * Attention, you have to drop association with old instance manually!
     */
    public function associate(EntityInterface $related = null)
    {
        //Removing association
        if ($related === null) {
            throw new RelationException(
                'Unable to associate null to HAS_ONE relation.'
            );
        }

        parent::associate($this->mountRelation($related));
    }

    /**
     * Create record and configure it's fields with relation data. Attention, you have to validate
     * and save record by your own. Newly created entity will not be associated automatically!
     * Pre-loaded data will not be altered, unless reset() method are called.
     *
     * @param mixed $fields
     *
     * @return RecordEntity
     */
    public function create($fields = [])
    {
        $record = call_user_func([$this->getClass(), 'create'], $fields, $this->orm);

        return $this->mountRelation($record);
    }

    /**
     * {@inheritdoc}
     */
    protected function mountRelation(EntityInterface $record)
    {
        //Key in child record
        $outerKey = $this->definition[RecordEntity::OUTER_KEY];

        //Key in parent record
        $innerKey = $this->definition[RecordEntity::INNER_KEY];

        if ($record->getField($outerKey, false) != $this->parent->getField($innerKey, false)) {
            $record->setField($outerKey, $this->parent->getField($innerKey, false));
        }

        if (!isset($this->definition[RecordEntity::MORPH_KEY])) {
            //No morph key presented
            return $record;
        }

        $morphKey = $this->definition[RecordEntity::MORPH_KEY];

        if ($record->getField($morphKey) != $this->parent->recordRole()) {
            $record->setField($morphKey, $this->parent->recordRole());
        }

        return $record;
    }

    /**
     * {@inheritdoc}
     */
    protected function createSelector()
    {
        $selector = parent::createSelector();

        //We are going to clarify selector manually (without loaders), that's easy relation
        if (isset($this->definition[RecordEntity::MORPH_KEY])) {
            $selector->where(
                $selector->primaryAlias() . '.' . $this->definition[RecordEntity::MORPH_KEY],
                $this->parent->recordRole()
            );
        }

        $selector->where(
            $selector->primaryAlias() . '.' . $this->definition[RecordEntity::OUTER_KEY],
            $this->parent->getField($this->definition[RecordEntity::INNER_KEY], false)
        );

        return $selector;
    }
}
