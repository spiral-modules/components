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
 * Must represent single migration status.
 */
interface StatusInterface
{
    /**
     * Migration statues.
     */
    const PENDING  = false;
    const EXECUTED = true;

    /**
     * Get migration given name.
     *
     * @return bool
     */
    public function getName();

    /**
     * Get migration state (EXECUTED or PENDING).
     *
     * @return bool
     */
    public function getState();

    /**
     * Get migration creation time.
     *
     * @return \DateTime
     */
    public function getTimeCreated();

    /**
     * Get migration execution time (if any).
     *
     * @return \DateTime|null
     */
    public function getTimeExecuted();
}