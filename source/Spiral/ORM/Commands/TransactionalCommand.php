<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Commands;

use Spiral\ORM\CommandInterface;
use Spiral\ORM\TransactionInterface;

/**
 * Command to handle multiple inner commands.
 */
class TransactionalCommand extends AbstractCommand implements TransactionInterface
{
    /**
     * Nested commands.
     *
     * @var CommandInterface[]
     */
    private $commands = [];

    /**
     * {@inheritdoc}
     */
    public function addCommand(CommandInterface $command)
    {
        $this->commands[] = $command;
    }

    /**
     * {@inheritdoc}
     */
    public function getCommands()
    {
        foreach ($this->commands as $command) {
            if ($command instanceof TransactionInterface) {
                yield from $command->getCommands();
            }

            yield $command;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function run()
    {
        //nothing to do (see getCommands())
    }

    public function execute()
    {
        //nothing to do (see getCommands())
    }

    public function complete()
    {
        //nothing to do (see getCommands())
    }

    public function rollBack()
    {
        //nothing to do (see getCommands())
    }
}