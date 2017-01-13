<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Commands;

use Spiral\ORM\CommandInterface;
use Spiral\ORM\Transaction;

/**
 * Command to handle multiple inner commands.
 */
class TransactionalCommand extends Transaction implements CommandInterface
{
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