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
                throw new ORMException("Only Insert and Update commands can be used as leading");
            }

            $this->leadingCommand = $command;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getContext(): array
    {
        if (empty($this->leadingCommand)) {
            throw new ORMException("Leading command is not set");
        }

        return $this->leadingCommand->getContext();
    }

    /**
     * {@inheritdoc}
     */
    public function addContext(string $name, $value)
    {
        if (empty($this->leadingCommand)) {
            throw new ORMException("Leading command is not set");
        }

        $this->leadingCommand->addContext($name, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getLeading(): ContextualCommandInterface
    {
        if (empty($this->leadingCommand)) {
            throw new ORMException("Leading command is not set");
        }

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
