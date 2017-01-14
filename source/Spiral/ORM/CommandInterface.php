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

    /**
     * Closure to be called after command executing.
     *
     * @param \Closure $closure
     */
    public function onExecute(\Closure $closure);

    /**
     * To be called after parent transaction been commited.
     *
     * @param \Closure $closure
     */
    public function onComplete(\Closure $closure);

    /**
     * To be called after parent transaction been rolled back.
     *
     * @param \Closure $closure
     */
    public function onRollBack(\Closure $closure);
}