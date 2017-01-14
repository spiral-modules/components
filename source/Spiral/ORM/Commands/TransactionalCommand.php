<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Commands;

use Spiral\ORM\CommandInterface;
use Spiral\ORM\ContextualCommandInterface;
use Spiral\ORM\Exceptions\ORMException;

/**
 * Command to handle multiple inner commands.
 */
class TransactionalCommand extends AbstractCommand implements
    \IteratorAggregate,
    ContextualCommandInterface
{
    /**
     * Nested commands.
     *
     * @var CommandInterface[]
     */
    private $commands = [];

    /**
     * @var ContextualCommandInterface
     */
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
            if (!$command instanceof ContextualCommandInterface) {
                throw new ORMException("Only Insert and Update commands can be used as leading.");
            }

            $this->leadingCommand = $command;
        }
    }

    public function getContext(): array
    {
        return $this->leadingCommand->getContext();
    }

    public function addContext(string $name, $value)
    {
        $this->leadingCommand->addContext($name, $value);
    }

    public function getLeading(): ContextualCommandInterface
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
