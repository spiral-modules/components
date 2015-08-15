<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Migrations;

use Spiral\Database\Entities\Database;

/**
 * Migration can be executed and rolled back at any moment, implementation is specific to spiral
 * Database implementation.
 */
interface MigrationInterface
{
    /**
     * Migration can request specific database to be altered. Migrator must supply it, however
     * migration status will be stored in primary migration database.
     *
     * @return null|string
     */
    public function requestedDatabase();

    /**
     * Configuring migration. This method will be automatically called after migration created and
     * used to resolve target database.
     *
     * @param Database $database
     */
    public function setDatabase(Database $database);

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
    public function getStatus();

    /**
     * Executing migration.
     */
    public function up();

    /**
     * Dropping (rollback) migration.
     */
    public function down();
}