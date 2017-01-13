<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM;

use Spiral\Models\ActiveEntityInterface;

/**
 * Adds ActiveRecord abilities to RecordEntity.
 */
abstract class Record extends RecordEntity implements ActiveEntityInterface
{
    /**
     * Sync entity with database, when no transaction is given ActiveRecord will create and run it
     * automatically.
     *
     * @param bool                      $queueRelations
     * @param TransactionInterface|null $transaction
     *
     * @return int
     */
    public function save(
        bool $queueRelations = true,
        TransactionInterface $transaction = null
    ): int {
        if (!$this->isLoaded()) {
            $state = self::CREATED;
        } elseif (!$this->hasChanges()) {
            $state = self::UPDATED;
        } else {
            $state = self::UNCHANGED;
        }

        if (empty($transaction)) {
            /*
             * When no transaction is given we will create our own and run it immediately.
             */
            $transaction = $transaction ?? new Transaction();
            $transaction->addCommand($this->queueStore($queueRelations));
            $transaction->run();
        } else {
            $transaction->addCommand($this->queueStore($queueRelations));
        }

        return $state;
    }

    /**
     * Delete entity in database, when no transaction is given ActiveRecord will create and run it
     * automatically.
     *
     * @param TransactionInterface|null $transaction
     */
    public function delete(TransactionInterface $transaction = null)
    {
        if (empty($transaction)) {
            /*
             * When no transaction is given we will create our own and run it immediately.
             */
            $transaction = $transaction ?? new Transaction();
            $transaction->addCommand($this->queueDelete());
            $transaction->run();
        } else {
            $transaction->addCommand($this->queueDelete());
        }
    }
}