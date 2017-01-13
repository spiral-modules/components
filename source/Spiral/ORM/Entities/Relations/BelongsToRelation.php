<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Entities\Relations;

use Spiral\ORM\RelationInterface;

class BelongsToRelation implements RelationInterface
{
    public function withData($data)
    {
        dump($data);

        return $this;
    }

    public function getRelated()
    {
        return 'author';
    }
}