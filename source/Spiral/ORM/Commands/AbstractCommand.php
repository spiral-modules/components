<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Commands;

use Spiral\ORM\CommandInterface;

/**
 * Provides support for command events.
 */
abstract class AbstractCommand implements CommandInterface
{
    /**
     * @var \Closure
     */
    private $onExecute = null;
    private $onComplete = null;
    private $onRollback = null;

    /**
     * Closure to be called after command executing.
     *
     * @param \Closure $closure
     */
    final public function onExecute(\Closure $closure)
    {
        $this->onExecute = $closure;
    }

    /**
     * To be called after parent transaction been commited.
     *
     * @param \Closure $closure
     */
    final public function onComplete(\Closure $closure)
    {
        $this->onComplete = $closure;
    }

    /**
     * To be called after parent transaction been rolled back.
     *
     * @param \Closure $closure
     */
    final public function onRollback(\Closure $closure)
    {
        $this->onRollback = $closure;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        if (!empty($this->onExecute)) {
            call_user_func($this->onExecute, $this);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function complete()
    {
        if (!empty($this->onComplete)) {
            call_user_func($this->onComplete, $this);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rollBack()
    {
        if (!empty($this->onRollback)) {
            call_user_func($this->onRollback, $this);
        }
    }
}