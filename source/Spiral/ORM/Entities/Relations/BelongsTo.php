<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM\Entities\Relations;

use Spiral\Models\EntityInterface;
use Spiral\ORM\Exceptions\RelationException;
use Spiral\ORM\ORM;
use Spiral\ORM\RecordEntity;

/**
 * Represents simple BELONGS_TO relation with ability to associate and de-associate parent.
 */
class BelongsTo extends HasOne
{
    /**
     * Relation type, required to fetch record class from relation definition.
     */
    const RELATION_TYPE = RecordEntity::BELONGS_TO;

    /**
     * {@inheritdoc}
     */
    public function isLoaded()
    {
        $this->fetchFromCache();

        if (empty($this->parent->getField($this->definition[RecordEntity::INNER_KEY], false))) {
            return true;
        }

        return $this->loaded;
    }

    /**
     * {@inheritdoc}
     */
    public function getRelated()
    {
        $this->fetchFromCache();

        return parent::getRelated();
    }

    /**
     * {@inheritdoc}
     *
     * Parent record MUST be saved in order to preserve parent association.
     */
    public function associate(EntityInterface $related = null)
    {
        if ($related === null) {
            $this->deassociate();

            return;
        }

        /**
         * @var RecordEntity $related
         */
        if (!$related->isLoaded()) {
            throw new RelationException(
                "Unable to set 'belongs to' parent, parent has be fetched from database."
            );
        }

        parent::associate($related);

        //Key in parent record
        $outerKey = $this->definition[RecordEntity::OUTER_KEY];

        //Key in child record
        $innerKey = $this->definition[RecordEntity::INNER_KEY];

        if ($this->parent->getField($innerKey, false) != $related->getField($outerKey, false)) {
            //We are going to set relation keys right on assertion
            $this->parent->setField($innerKey, $related->getField($outerKey, false));
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function mountRelation(EntityInterface $record)
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
        if (empty($this->parent->getField($this->definition[RecordEntity::INNER_KEY], false))) {
            throw new RelationException(
                "Belongs-to selector can not be constructed when inner key ("
                . $this->definition[RecordEntity::INNER_KEY]
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
        if (!$this->definition[RecordEntity::NULLABLE]) {
            throw new RelationException(
                "Unable to de-associate relation data, relation is not nullable."
            );
        }

        $innerKey = $this->definition[RecordEntity::INNER_KEY];
        $this->parent->setField($innerKey, null);

        $this->loaded = true;
        $this->instance = null;
        $this->data = [];
    }

    /**
     * Try to fetch outer model using entity cache.
     */
    private function fetchFromCache()
    {
        if ($this->loaded) {
            return;
        }

        if (
        empty($key = $this->parent->getField($this->definition[RecordEntity::INNER_KEY], false))
        ) {
            return;
        }

        if (empty($this->definition[ORM::M_PRIMARY_KEY])) {
            //Linked not by primary key
            return;
        }

        if (empty($entity = $this->orm->getEntity($this->getClass(), $key))) {
            return;
        }

        $this->loaded = true;
        $this->instance = $entity;
    }
}