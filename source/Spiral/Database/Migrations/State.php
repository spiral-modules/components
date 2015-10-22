<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Database\Migrations;

/**
 * Default implementation of migration status interface.
 */
class State implements StateInterface
{
    /**
     * @var string
     */
    private $name = '';

    /**
     * @var bool
     */
    private $status = self::PENDING;

    /**
     * @var \DateTime|null
     */
    private $timeCreated = null;

    /**
     * @var \DateTime|null
     */
    private $timeExecuted = null;

    /**
     * @param string    $name
     * @param bool      $status
     * @param \DateTime $timeCreated
     * @param \DateTime $timeExecuted
     */
    public function __construct(
        $name,
        $status,
        \DateTime $timeCreated,
        \DateTime $timeExecuted = null
    ) {
        $this->name = $name;
        $this->status = $status;
        $this->timeCreated = $timeCreated;
        $this->timeExecuted = $timeExecuted;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * {@inheritdoc}
     */
    public function getTimeCreated()
    {
        return $this->timeCreated;
    }

    /**
     * {@inheritdoc}
     */
    public function getTimeExecuted()
    {
        return $this->timeExecuted;
    }
}