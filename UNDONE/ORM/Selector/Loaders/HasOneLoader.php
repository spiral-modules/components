<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM\Selector\Loaders;

use Spiral\ORM\Model;
use Spiral\ORM\ORM;
use Spiral\ORM\Selector;
use Spiral\ORM\Selector\Loader;

class HasOneLoader extends Loader
{
    /**
     * Relation type is required to correctly resolve foreign model.
     */
    const RELATION_TYPE = Model::HAS_ONE;

    /**
     * Default load method (inload or postload).
     */
    const LOAD_METHOD = Selector::INLOAD;

    /**
     * Internal loader constant used to decide nested aggregation level.
     */
    const MULTIPLE = false;

    /**
     * {@inheritdoc}
     */
    public function createSelector()
    {
        if (empty($selector = parent::createSelector()))
        {
            return null;
        }

        if (empty($this->parent))
        {
            //No need for where conditions
            return $selector;
        }

        //Mounting where conditions
        $this->mountConditions($selector);

        //Aggregated keys (example: all parent ids)
        if (empty($aggregatedKeys = $this->parent->getAggregatedKeys($this->getReferenceKey())))
        {
            //Nothing to postload, no parents
            return null;
        }

        //Adding condition
        $selector->where($this->getKey(Model::OUTER_KEY), 'IN', $aggregatedKeys);

        return $selector;
    }

    /**
     * {@inheritdoc}
     */
    protected function clarifySelector(Selector $selector)
    {
        $selector->join($this->joinType(), $this->getTable() . ' AS ' . $this->getAlias(), [
            $this->getKey(Model::OUTER_KEY) => $this->getParentKey()
        ]);

        $this->mountConditions($selector);
    }

    /**
     * {@inheritdoc}
     */
    protected function mountConditions(Selector $selector)
    {
        if (!empty($morphKey = $this->getKey(Model::MORPH_KEY)))
        {
            if ($this->isJoined())
            {
                $selector->onWhere($morphKey, $this->parent->schema[ORM::E_ROLE_NAME]);
            }
            else
            {
                $selector->where($morphKey, $this->parent->schema[ORM::E_ROLE_NAME]);
            }
        }

        return $selector;
    }
}