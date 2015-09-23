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
use Spiral\ORM\Record;

/**
 * Represent simple HAS_ONE relation with ability to associate and de-associate records.
 */
class HasOne extends Relation
{
    /**
     * Relation type, required to fetch record class from relation definition.
     */
    const RELATION_TYPE = Record::HAS_ONE;

    /**
     * {@inheritdoc}
     *
     * Attention, while associating old instance will not be removed or de-associated automatically!
     */
    public function associate($related = null)
    {
        //Removing association
        if (static::MULTIPLE === false && $related === null) {
            if (!$this->definition[Record::NULLABLE]) {
                throw new RelationException(
                    "Unable to de-associate relation data, relation is not nullable."
                );
            }
        }

        //todo: preload related instance!
        if (!empty($this->instance) && $this->instance != $related) {
            $this->deassociate();
        }

        parent::associate($related);
        $this->mountRelation($related);
    }

    /**
     * Create record and configure it's fields with relation data. Attention, you have to validate
     * and save record by your own. Newly created entity will not be associated automatically!
     * Pre-loaded data will not be altered, unless reset() method are called.
     *
     * @param mixed $fields
     * @return Record
     */
    public function create($fields = [])
    {
        $record = call_user_func([$this->getClass(), 'create'], $fields, $this->orm);

        return $this->mountRelation($record);
    }

    /**
     * {@inheritdoc}
     */
    protected function mountRelation(Record $record)
    {
        //Key in child record
        $outerKey = $this->definition[Record::OUTER_KEY];

        //Key in parent record
        $innerKey = $this->definition[Record::INNER_KEY];

        if ($record->getField($outerKey, false) != $this->parent->getField($innerKey, false)) {
            $record->setField($outerKey, $this->parent->getField($innerKey, false), false);
        }

        if (!isset($this->definition[Record::MORPH_KEY])) {
            //No morph key presented
            return $record;
        }

        $morphKey = $this->definition[Record::MORPH_KEY];

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
        if (isset($this->definition[Record::MORPH_KEY])) {
            $selector->where(
                $selector->getPrimaryAlias() . '.' . $this->definition[Record::MORPH_KEY],
                $this->parent->recordRole()
            );
        }

        $selector->where(
            $selector->getPrimaryAlias() . '.' . $this->definition[Record::OUTER_KEY],
            $this->parent->getField($this->definition[Record::INNER_KEY], false)
        );

        return $selector;
    }

    /**
     * De associate related record.
     */
    protected function deassociate()
    {
        $related = $this->getRelated();
        if ($related instanceof Record) {
            $related->setField($this->definition[Record::OUTER_KEY], null, false);

            if (isset($this->definition[Record::MORPH_KEY])) {
                //Dropping morph key value
                $related->setField($this->definition[Record::MORPH_KEY], null);
            }

            if (!$related->save()) {
                throw new RelationException(
                    "Unable to de-associate existed and already related record, unable to save."
                );
            }
        }

        $this->loaded = true;
        $this->instance = null;
        $this->data = [];
    }
}