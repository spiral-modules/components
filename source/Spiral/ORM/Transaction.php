<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM;

use Spiral\ORM\Exceptions\RecordException;

/**
 * Singular ORM transaction with ability to automatically open transaction for all involved
 * drivers.
 *
 * Drivers will be automatically fetched from commands.
 */
class Transaction implements TransactionInterface
{
    /**
     * @var CommandInterface[]
     */
    private $commands = [];

    /**
     * Store entity information (update or insert).
     *
     * @param RecordInterface $record
     *
     * @throws RecordException
     */
    public function store(RecordInterface $record)
    {
        $this->addCommand($record->queueStore());
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
     * {@inheritdoc}
     *
     * Executing transaction.
     */
    public function run()
    {
        //Related DBAL drivers
        $drivers = [];

        try {
            foreach ($this->commands as $command) {
                if ($command instanceof SQLCommandInterface) {
                    $driver = $command->getDriver();

                    if (in_array($driver, $drivers)) {
                        //Command requires DBAL driver to open transaction
                        $drivers[] = $driver;
                        $driver->beginTransaction();
                    }
                }

                //Execute command
                $command->execute();
            }
        } catch (\Throwable $e) {
            foreach (array_reverse($drivers) as $driver) {
                $driver->rollbackTransaction();
            }

            foreach (array_reverse($this->commands) as $command) {
                $command->rollBack();
            }

            throw $e;
        }

        foreach ($drivers as $driver) {
            $driver->commitTransaction();
        }

        foreach ($this->commands as $command) {
            $command->complete();
        }
    }
}