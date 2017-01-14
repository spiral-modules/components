<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Entities\Relations;

use Spiral\ORM\Commands\NullCommand;
use Spiral\ORM\ContextualCommandInterface;
use Spiral\ORM\Entities\RecordIterator;

class HasManyRelation extends AbstractRelation implements \IteratorAggregate
{
    /**
     * Has many relation represent itself (see getIterator method).
     *
     * @return $this
     */
    public function getRelated()
    {
        return $this;
    }


    public function queueCommands(ContextualCommandInterface $command)
    {
        return new NullCommand();
    }

    public function getIterator()
    {
        //think about it?
        return new RecordIterator(
            $this->data,
            $this->class,
            $this->orm
        );
    }

    public function setRelated()
    {
    }

    public function has()
    {
    }

    public function add()
    {
    }

    public function delete()
    {
    }
}