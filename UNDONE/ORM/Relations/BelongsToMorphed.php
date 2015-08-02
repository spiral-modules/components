<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM\Relations;

use Spiral\ORM\Model;
use Spiral\ORM\ModelIterator;
use Spiral\ORM\ORMException;
use Spiral\ORM\Selector;

class BelongsToMorphed extends BelongsTo
{
    /**
     * Relation type.
     */
    const RELATION_TYPE = Model::BELONGS_TO_MORPHED;

    /**
     * {@inheritdoc}
     */
    protected function getMorphedClass()
    {
        $morphKey = $this->definition[Model::MORPH_KEY];

        return $this->getClass()[$this->parent->getField($morphKey)];
    }

    /**
     * {@inheritdoc}
     */
    protected function createModel()
    {
        return $this->instance = $this->orm->construct($this->getMorphedClass(), $this->data);
    }

    /**
     * {@inheritdoc}
     */
    protected function createSelector()
    {
        $selector = new Selector($this->getMorphedClass(), $this->orm);

        return $selector->where(
            $selector->getPrimaryAlias() . '.' . $this->definition[Model::OUTER_KEY],
            $this->parent->getField($this->definition[Model::INNER_KEY], false)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function setInstance(Model $instance = null)
    {
        parent::setInstance($instance);

        if (is_null($instance))
        {
            return;
        }

        //Forcing morph key
        $morphKey = $this->definition[Model::MORPH_KEY];
        $this->parent->setField($morphKey, $instance->getRoleName(), false);
    }

    /**
     * {@inheritdoc}
     */
    protected function dropRelation()
    {
        parent::dropRelation();

        $morphKey = $this->definition[Model::MORPH_KEY];
        $this->parent->setField($morphKey, null);
    }
}