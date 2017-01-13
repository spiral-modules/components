<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM;

interface CommandInterface
{
    /**
     * Executes command.
     */
    public function execute();

    /**
     * Complete command, method to be called when all other commands are already executed and
     * transaction is closed.
     */
    public function complete();

    /**
     * Rollback command or declare that command been rolledback.
     */
    public function rollBack();
}