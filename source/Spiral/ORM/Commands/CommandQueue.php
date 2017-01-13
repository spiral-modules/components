<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Commands;

use Spiral\ORM\CommandInterface;

class CommandQueue implements CommandInterface
{
    private $commands = [];

    public function addCommand(CommandInterface $command)
    {
        if ($command instanceof NullCommand) {
            //Nothing to do, let's save some memory
            return;
        }

        $this->commands[] = $command;
    }
}