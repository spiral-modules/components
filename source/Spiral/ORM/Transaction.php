<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM;

use Spiral\Database\Entities\Driver;
use Spiral\ORM\Exceptions\RecordException;

/**
 * Singular ORM transaction with ability to automatically open transaction for all involved
 * drivers.
 *
 * Drivers will be automatically fetched from commands. Potentially Transaction can be improved
 * to optimize commands inside it (batch insert, batch delete and etc).
 */
final class Transaction implements TransactionInterface
{
    /**
     * @var CommandInterface[]
     */
    private $commands = [];

    /**
     * Store entity information (update or insert).
     *
     * @param RecordInterface $record
     * @param bool            $queueRelations
     *
     * @throws RecordException
     */
    public function store(RecordInterface $record, bool $queueRelations = true)
    {
        $this->addCommand($record->queueStore($queueRelations));
    }

    /**
     * Delete entity from database.
     *
     * @param RecordInterface $record
     *
     * @throws RecordException
     */
    public function delete(RecordInterface $record)
    {
        $this->addCommand($record->queueDelete());
    }

    /**
     * {@inheritdoc}
     */
    public function addCommand(CommandInterface $command)
    {
        $this->commands[] = $command;
    }

    /**
     * Will return flattened list of commands.
     *
     * @return \Generator
     */
    public function getCommands()
    {
        foreach ($this->commands as $command) {
            if ($command instanceof \Traversable) {
                //Array of commands
                yield from $command;
            }

            yield $command;
        }
    }

    /**
     * {@inheritdoc}
     *
     * Executing transaction. Method require minor refactoring.
     */
    public function run()
    {
        /**
         * @var Driver[]           $drivers
         * @var CommandInterface[] $commands
         */
        $drivers = [];
        $commands = [];

        foreach ($this->getCommands() as $command) {
            if ($command instanceof SQLCommandInterface) {
                $driver = $command->getDriver();
                if (!empty($driver) && !in_array($driver, $drivers)) {
                    $drivers[] = $driver;
                }
            }

            $commands[] = $command;
        }

        $executedCommands = [];
        $wrappedDrivers = [];

        try {
            if (count($commands) > 1) {
                //Starting transactions
                foreach ($drivers as $driver) {
                    $driver->beginTransaction();
                    $wrappedDrivers[] = $driver;
                }
            }

            //Run commands
            foreach ($commands as $command) {
                $command->execute();
                $executedCommands[] = $command;
            }
        } catch (\Throwable $e) {
            foreach (array_reverse($wrappedDrivers) as $driver) {
                /** @var Driver $driver */
                $driver->rollbackTransaction();
            }

            foreach (array_reverse($executedCommands) as $command) {
                /** @var CommandInterface $command */
                $command->rollBack();
            }

            $this->commands = [];
            throw $e;
        }

        foreach ($wrappedDrivers as $driver) {
            $driver->commitTransaction();
        }

        foreach ($executedCommands as $command) {
            $command->complete();
        }

        $this->commands = [];
    }
}