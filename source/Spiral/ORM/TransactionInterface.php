<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM;

interface TransactionInterface
{
    public function addCommand(CommandInterface $command);

    public function commit();
}