<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Entities\Relations;

class HasManyRelation extends AbstractRelation
{

    public function getRelated()
    {
        return $this;
    }
}