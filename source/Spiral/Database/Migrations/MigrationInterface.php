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
 * Migration can be executed and rolled back at any moment, implementation is specific to spiral
 * Database implementation.
 */
interface MigrationInterface
{
    /**
     * Migration status must be supplied by MigratorInterface and describe migration state.
     *
     * @param StatusInterface $status
     */
    public function setStatus(StatusInterface $status);

    /**
     * Migration status are filled by MigratorInterface and describes status of migration. Can be
     * empty for new migrations.
     *
     * @return StatusInterface|null
     */
    public function status();

    /**
     * Executing migration.
     */
    public function up();

    /**
     * Dropping (rollback) migration.
     */
    public function down();
}