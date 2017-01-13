<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Commands;

use Spiral\ORM\CommandInterface;

/**
 * Command to handle multiple inner commands.
 */
class TransactionalCommand extends AbstractCommand implements \IteratorAggregate
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
    public function getIterator()
    {
        foreach ($this->commands as $command) {
            if ($command instanceof \Traversable) {
                yield from $command;
            }

            yield $command;
        }
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