<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\ORM\Entities\Loaders;

use Spiral\ORM\Entities\Selector;
use Spiral\ORM\Entities\WhereDecorator;
use Spiral\ORM\RecordEntity;

/**
 * Dedicated to load HAS_MANY relation data, POSTLOAD is preferred loading method. Additional where
 * conditions and morph keys are supported.
 */
class HasManyLoader extends HasOneLoader
{
    /**
     * Relation type is required to correctly resolve foreign record class based on relation
     * definition.
     */
    const RELATION_TYPE = RecordEntity::HAS_MANY;

    /**
     * Default load method (inload or postload).
     */
    const LOAD_METHOD = self::POSTLOAD;

    /**
     * Internal loader constant used to decide how to aggregate data tree, true for relations like
     * MANY TO MANY or HAS MANY.
     */
    const MULTIPLE = true;

    /**
     * {@inheritdoc}
     *
     * Where conditions will be mounted using WhereDecorator to unify logic between POSTLOAD and
     * INLOAD methods.
     */
    protected function mountConditions(Selector $selector)
    {
        $selector = parent::mountConditions($selector);

        //Let's use where decorator to set conditions, it will automatically route tokens to valid
        //destination (JOIN or WHERE)
        $decorator = new WhereDecorator(
            $selector, $this->isJoinable() ? 'onWhere' : 'where', $this->getAlias()
        );

        if (!empty($this->definition[RecordEntity::WHERE])) {
            //Relation WHERE conditions
            $decorator->where($this->definition[RecordEntity::WHERE]);
        }

        //User specified WHERE conditions
        if (!empty($this->options['where'])) {
            $decorator->where($this->options['where']);
        }
    }
}