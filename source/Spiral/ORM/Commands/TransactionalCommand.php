<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Commands;

use Spiral\ORM\CommandInterface;
use Spiral\ORM\ContextualCommandInterface;

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

    private $leadingCommand;

    /**
     * {@inheritdoc}
     */
    public function addCommand(CommandInterface $command, bool $leading = false)
    {
        if ($command instanceof NullCommand) {
            return;
        }

        $this->commands[] = $command;

        if ($leading) {
            $this->leadingCommand = $command;
        }
    }

    /**
     * @return mixed
     */
    public function getLeadingCommand(): ContextualCommandInterface
    {
        return $this->leadingCommand;
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
}
