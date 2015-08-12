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
use Spiral\ORM\ORMException;

class BelongsTo extends HasOne
{
    /**
     * Relation type.
     */
    const RELATION_TYPE = Model::BELONGS_TO;

    /**
     * {@inheritdoc}
     */
    public function associate(Model $related = null)
    {
        if (is_null($related))
        {
            $this->dropRelation();

            return;
        }

        parent::associate($related);

        /**
         * @var Model $related
         */
        if (!$related->isLoaded())
        {
            throw new ORMException(
                "Unable to set 'belongs to' parent, parent has be fetched from database."
            );
        }

        //Key in parent model
        $outerKey = $this->definition[Model::OUTER_KEY];

        //Key in child model
        $innerKey = $this->definition[Model::INNER_KEY];

        if ($this->parent->getField($innerKey, false) != $related->getField($outerKey, false))
        {
            //We are going to set relation keys right on assertion
            $this->parent->setField($innerKey, $related->getField($outerKey, false), false);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function dropRelation()
    {
        $innerKey = $this->definition[Model::INNER_KEY];
        $this->parent->setField($innerKey, null, false);
    }

    /**
     * {@inheritdoc}
     */
    protected function mountRelation(Model $model)
    {
        //Nothing to do, children can not update parent relation
        return $model;
    }
}