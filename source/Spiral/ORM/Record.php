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
        //saturate transaction
        $transaction->addCommand($command = $this->queueSave($queueRelations));

        if ($command instanceof InsertCommand) {
            return self::CREATED;
        } elseif ($command instanceof UpdateCommand) {
            return self::UPDATED;
        }

        return self::UNCHANGED;
    }

    public function delete(TransactionInterface $transaction = null)
    {
        //saturate transaction

        $transaction->addCommand($this->queueDelete());
    }
}