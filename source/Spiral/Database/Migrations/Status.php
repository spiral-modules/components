<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Migrations;

/**
 * Default implementation of migration status interface.
 */
class Status implements StatusInterface
{
    /**
     * @var string
     */
    private $name = '';

    /**
     * @var bool
     */
    private $state = self::PENDING;

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
     * @param bool      $state
     * @param \DateTime $timeCreated
     * @param \DateTime $timeExecuted
     */
    public function __construct($name, $state, \DateTime $timeCreated, \DateTime $timeExecuted = null)
    {
        $this->name = $name;
        $this->state = $state;
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
    public function getState()
    {
        return $this->state;
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