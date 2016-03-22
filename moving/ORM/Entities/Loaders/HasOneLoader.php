<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\ORM\Entities\Loaders;

use Spiral\Database\Injections\Parameter;
use Spiral\ORM\Entities\Loader;
use Spiral\ORM\Entities\RecordSelector;
use Spiral\ORM\ORM;
use Spiral\ORM\RecordEntity;

/**
 * Dedicated to load HAS_ONE relations, by default loader will prefer to join data into query.
 * Loader support MORPH_KEY.
 */
class HasOneLoader extends Loader
{
    /**
     * Relation type is required to correctly resolve foreign record class based on relation
     * definition.
     */
    const RELATION_TYPE = RecordEntity::HAS_ONE;

    /**
     * Default load method (inload or postload).
     */
    const LOAD_METHOD = self::INLOAD;

    /**
     * Internal loader constant used to decide how to aggregate data tree, true for relations like
     * MANY TO MANY or HAS MANY.
     */
    const MULTIPLE = false;

    /**
     * {@inheritdoc}
     */
    public function createSelector()
    {
        if (empty($selector = parent::createSelector())) {
            return;
        }

        if (empty($this->parent)) {
            //No need for where conditions
            return $selector;
        }

        //Mounting where conditions
        $this->mountConditions($selector);

        //Aggregated keys (example: all parent ids)
        if (empty($aggregatedKeys = $this->parent->aggregatedKeys($this->getReferenceKey()))) {
            //Nothing to postload, no parents
            return;
        }

        //Adding condition
        $selector->where(
            $this->getKey(RecordEntity::OUTER_KEY),
            'IN',
            new Parameter($aggregatedKeys)
        );

        return $selector;
    }

    /**
     * {@inheritdoc}
     */
    protected function clarifySelector(RecordSelector $selector)
    {
        $selector->join($this->joinType(), $this->getTable() . ' AS ' . $this->getAlias(), [
            $this->getKey(RecordEntity::OUTER_KEY) => $this->getParentKey(),
        ]);

        $this->mountConditions($selector);
    }

    /**
     * Mount additional (not related to parent key) conditions, extended by child loaders
     * (HAS_MANY, BELONGS_TO).
     *
     * @param RecordSelector $selector
     *
     * @return RecordSelector
     */
    protected function mountConditions(RecordSelector $selector)
    {
        //We only going to mount morph key as additional condition
        if (!empty($morphKey = $this->getKey(RecordEntity::MORPH_KEY))) {
            if ($this->isJoinable()) {
                $selector->onWhere($morphKey, $this->parent->schema[ORM::M_ROLE_NAME]);
            } else {
                $selector->where($morphKey, $this->parent->schema[ORM::M_ROLE_NAME]);
            }
        }

        return $selector;
    }
}
