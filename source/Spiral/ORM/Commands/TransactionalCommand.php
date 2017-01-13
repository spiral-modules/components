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
    /**
     * Execute command = push transaction.
     */
    public function execute()
    {
        $this->run();
    }

    public function callEvent(int $event)
    {
        // TODO: Implement setState() method.
    }
}