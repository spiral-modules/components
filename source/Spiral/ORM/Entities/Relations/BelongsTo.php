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
use Spiral\ORM\Record;

/**
 * Represents simple BELONGS_TO relation with ability to associate and de-associate parent.
 *
 * @todo: Check if relation has inversed version and bypass parent data into it to avoid looped
 *        queries.
 */
class BelongsTo extends HasOne
{
    /**
     * Relation type, required to fetch record class from relation definition.
     */
    const RELATION_TYPE = Record::BELONGS_TO;

    /**
     * {@inheritdoc}
     */
    public function isLoaded()
    {
        if (empty($this->parent->getField($this->definition[Record::INNER_KEY], false))) {
            return true;
        }

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
        //TODO: CHECK ENTITY CACHE
        return parent::getRelated();
    }

    /**
     * {@inheritdoc}
     *
     * Parent record MUST be saved in order to preserve parent association.
     */
    public function associate($related = null)
    {
        if ($related === null) {
            $this->deassociate();

            return;
        }

        /**
         * @var Record $related
         */
        if (!$related->isLoaded()) {
            throw new RelationException(
                "Unable to set 'belongs to' parent, parent has be fetched from database."
            );
        }

        parent::associate($related);

        //Key in parent record
        $outerKey = $this->definition[Record::OUTER_KEY];

        //Key in child record
        $innerKey = $this->definition[Record::INNER_KEY];

        if ($this->parent->getField($innerKey, false) != $related->getField($outerKey, false)) {
            //We are going to set relation keys right on assertion
            $this->parent->setField($innerKey, $related->getField($outerKey, false), false);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function mountRelation(Record $record)
    {
        //Nothing to do, children can not update parent relation
        return $record;
    }

    /**
     * {@inheritdoc}
     *
     * @throws RelationException
     */
    protected function createSelector()
    {
        if (empty($this->parent->getField($this->definition[Record::INNER_KEY], false))) {
            throw new RelationException(
                "Belongs-to selector can not be constructed when inner key ("
                . $this->definition[Record::INNER_KEY]
                . ") is null."
            );
        }

        return parent::createSelector();
    }

    /**
     * {@inheritdoc}
     *
     * Belongs-to can not automatically create parent.
     */
    protected function emptyRecord()
    {
        return null;
    }

    /**
     * De associate related record.
     */
    protected function deassociate()
    {
        if (!$this->definition[Record::NULLABLE]) {
            throw new RelationException(
                "Unable to de-associate relation data, relation is not nullable."
            );
        }

        $innerKey = $this->definition[Record::INNER_KEY];
        $this->parent->setField($innerKey, null, false);

        $this->loaded = true;
        $this->instance = null;
        $this->data = [];
    }
}