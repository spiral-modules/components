<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Migrations;

use Spiral\Database\Database;

interface MigratorInterface
{
    /**
     * Configuring migrator with specific database to work with.
     *
     * @param Database $database
     */
    public function setDatabase(Database $database);

    /**
     * Check if migrator are set and can be used. Default migrator will check that migrations table
     * exists in associated database.
     *
     * @return bool
     */
    public function isConfigured();

    /**
     * Configure migrator (create tables, files and etc).
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
     * @throws MigrationException
     */
    public function registerMigration($name, $class);

    /**
     * Run one outstanding migration, migrations will be performed in an order they were registered.
     * Method must return executed migration.
     *
     * @return MigrationInterface|null
     */
    public function run();

    /**
     * Rollback last executed migration. Method must return rolled back migration.
     *
     * @return MigrationInterface|null
     */
    public function rollback();
}