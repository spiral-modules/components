<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM;

use Spiral\Models\ActiveEntityInterface;
use Spiral\ORM\Commands\InsertCommand;
use Spiral\ORM\Commands\UpdateCommand;

/**
 * Adds ActiveRecord abilities to RecordEntity.
 */
abstract class Record extends RecordEntity implements ActiveEntityInterface
{
    public function save(TransactionInterface $transaction = null, bool $queueRelations = true): int
    {
        //Initial reacord command
        $command = $this->queueSave(false);

        if ($command instanceof InsertCommand) {
            $state = self::CREATED;
        } elseif ($command instanceof UpdateCommand) {
            $state = self::UPDATED;
        } else {
            $state = self::UNCHANGED;
        }

        if ($queueRelations) {
            //Mounting relation related updates
            $command = $this->relations->queueRelations($command);
        }

        //todo: saturate command

        //Registering command
        $transaction->addCommand($command);

        return $state;
    }

    public function delete(TransactionInterface $transaction = null)
    {
        //todo: saturate command

        //saturate transaction
        $transaction->addCommand($this->queueDelete());
    }
}