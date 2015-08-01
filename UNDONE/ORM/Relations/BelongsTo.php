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
     * Set relation data (called via __set method of parent ActiveRecord).
     *
     * Example:
     * $user->profile = new Profile();
     *
     * @param Model $instance
     * @throws ORMException
     */
    public function setInstance(Model $instance = null)
    {
        if (is_null($instance))
        {
            $this->dropRelation();

            return;
        }

        parent::setInstance($instance);

        /**
         * @var Model $instance
         */
        if (!$instance->isLoaded())
        {
            throw new ORMException(
                "Unable to set 'belongs to' parent, parent has be fetched from database."
            );
        }

        //Key in parent model
        $outerKey = $this->definition[Model::OUTER_KEY];

        //Key in child model
        $innerKey = $this->definition[Model::INNER_KEY];

        if ($this->parent->getField($innerKey, false) != $instance->getField($outerKey, false))
        {
            //We are going to set relation keys right on assertion
            $this->parent->setField($innerKey, $instance->getField($outerKey, false), false);
        }
    }

    /**
     * Drop relation keys.
     */
    protected function dropRelation()
    {
        $innerKey = $this->definition[Model::INNER_KEY];
        $this->parent->setField($innerKey, null, false);
    }

    /**
     * Mount relation keys to parent or children models to ensure their connection.
     *
     * @param Model $model
     * @return Model
     */
    protected function mountRelation(Model $model)
    {
        //Nothing to do, children can not update parent relation
        return $model;
    }
}