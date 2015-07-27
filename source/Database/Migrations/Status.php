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
     * Migration state (EXECUTED or PENDING).
     *
     * @var bool
     */
    protected $state = self::PENDING;

    /**
     * Migration execution time (if any).
     *
     * @var \DateTime|null
     */
    protected $timeExecuted = null;

    /**
     * Migration status instance.
     *
     * @param bool      $state
     * @param \DateTime $timeExecuted
     */
    public function __construct($state, \DateTime $timeExecuted = null)
    {
        $this->state = $state;
        $this->timeExecuted = $timeExecuted;
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
     * Get migration execution time (if any).
     *
     * @return \DateTime|null
     */
    public function getTimeExecuted()
    {
        return $this->timeExecuted;
    }
}