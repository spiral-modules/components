<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Entities\Relations;

use Spiral\ORM\Commands\InsertCommand;
use Spiral\ORM\Commands\NullCommand;
use Spiral\ORM\Commands\TransactionalCommand;
use Spiral\ORM\ContextualCommandInterface;
use Spiral\ORM\ORMInterface;
use Spiral\ORM\Record;

class HasOneRelation extends SingularRelation
{
    private $changed = true;

    public function queueCommands(ContextualCommandInterface $command)
    {
        if (empty($this->instance)) {
            return new NullCommand();
        }

        $related = $this->instance->queueStore(true);

        if ($related instanceof TransactionalCommand) {
            $related = $related->getLeadingCommand();
        }

        //Primary key of parent entity
        $primaryKey = $this->orm->define(get_class($this->parent), ORMInterface::R_PRIMARY_KEY);

        //todo: careful there
        if (
            $command instanceof InsertCommand
            && $primaryKey == $this->schema[Record::INNER_KEY]
        ) {
            /**
             * Particular case when parent entity exists but now saved yet AND outer key is PK.
             *
             * Basically inversed case of BELONGS_TO.
             */
            $command->onExecute(function (InsertCommand $command) use ($related) {
                $related->addContext($this->schema[Record::OUTER_KEY], $command->getInsertID());
            });
        } elseif ($this->changed && $related instanceof ContextualCommandInterface) {
            //Delete old one!
            $related->addContext(
                $this->schema[Record::OUTER_KEY],
                $this->parent->getField($this->schema[Record::INNER_KEY])
            );
        }

        return $related;
    }
}