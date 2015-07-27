<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Migrations;

interface StatusInterface
{
    /**
     * Migration statues.
     */
    const PENDING  = false;
    const EXECUTED = true;

    /**
     * Get migration state (EXECUTED or PENDING).
     *
     * @return bool
     */
    public function getState();

    /**
     * Get migration execution time (if any).
     *
     * @return \DateTime|null
     */
    public function getTimeExecuted();
}