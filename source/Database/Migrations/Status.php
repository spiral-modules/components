<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Migrations;

class Status implements StatusInterface
{
    /**
     * Migration given name.
     *
     * @var string
     */
    protected $name = '';

    /**
     * Migration state (EXECUTED or PENDING).
     *
     * @var bool
     */
    protected $state = self::PENDING;

    /**
     * Migration creation time.
     *
     * @var \DateTime|null
     */
    protected $timeCreated = null;

    /**
     * Migration execution time (if any).
     *
     * @var \DateTime|null
     */
    protected $timeExecuted = null;

    /**
     * Migration status instance.
     *
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
     * Get migration given name.
     *
     * @return bool
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get migration state (EXECUTED or PENDING).
     *
     * @return bool
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * Get migration creation time.
     *
     * @return \DateTime
     */
    public function getTimeCreated()
    {
        return $this->timeCreated;
    }

    /**
     * Get migration execution time (if any).
     *
     * @return \DateTime|null
     */
    public function getTimeExecuted()
    {
        return $this->timeExecuted;
    }
}