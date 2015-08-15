<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Migrations;

use Spiral\Database\Exceptions\MigrationException;
use Spiral\Database\Exceptions\MigratorException;

/**
 * Class responsible for migration process, implementation is specific to spiral Database implementation.
 */
interface MigratorInterface
{
    /**
     * Check if migrator are set and can be used. Default migrator will check that migrations table
     * exists in associated database.
     *
     * @return bool
     */
    public function isConfigured();

    /**
     * Configure migrator (create tables, files and etc).
     *
     * @throws MigrationException
     */
    public function configure();

    /**
     * Get list of all migrations. Every migration should have status filled.
     *
     * @return MigrationInterface[]
     */
    public function getMigrations();

    /**
     * Used to register new migration by migration class name. Migrator implementation should store
     * migration class in it's storage.
     *
     * Examples:
     * $repository->registerMigration('create_blog_tables', 'Vendor\Blog\Migrations\BlogTables');
     *
     * @param string $name  Migration name.
     * @param string $class Class name to represent migration.
     * @return bool
     * @throws MigratorException
     */
    public function registerMigration($name, $class);

    /**
     * Run one outstanding migration, migrations will be performed in an order they were registered.
     * Method must return executed migration.
     *
     * @return MigrationInterface|null
     * @throws MigrationException
     */
    public function run();

    /**
     * Rollback last executed migration. Method must return rolled back migration.
     *
     * @return MigrationInterface|null
     * @throws MigrationException
     */
    public function rollback();
}