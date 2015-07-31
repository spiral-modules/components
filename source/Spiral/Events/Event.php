<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Events;

/**
 * {@inheritdoc}
 */
class Event implements EventInterface
{
    /**
     * @var string
     */
    private $name = '';

    /**
     * @var mixed
     */
    private $context = null;

    /**
     * Event being stopped.
     *
     * @var bool
     */
    private $stopped = false;

    /**
     * @param string $name
     * @param mixed  $context
     */
    public function __construct($name, $context)
    {
        $this->name = $name;
        $this->context = $context;
    }

    /**
     * {@inheritdoc}
     */
    public function name()
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function &context()
    {
        return $this->context;
    }

    /**
     * {@inheritdoc}
     */
    public function isStopped()
    {
        return $this->stopped;
    }

    /**
     * {@inheritdoc}
     */
    public function stopPropagation()
    {
        $this->stopped = true;
    }

    /**
     * To clean context.
     */
    public function __destruct()
    {
        $this->context = null;
    }
}