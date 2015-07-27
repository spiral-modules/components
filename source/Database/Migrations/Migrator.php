<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Migrations;

use Spiral\Core\Component;
use Spiral\Core\ConfiguratorInterface;
use Spiral\Core\ContainerInterface;
use Spiral\Core\Traits\ConfigurableTrait;
use Spiral\Database\Database;
use Spiral\Database\Table;
use Spiral\Files\FilesInterface;
use Spiral\Tokenizer\TokenizerInterface;

class Migrator extends Component implements MigratorInterface
{
    /**
     * Configuration.
     */
    use ConfigurableTrait;

    /**
     * Container instance.
     *
     * @var ContainerInterface
     */
    protected $container = null;

    /**
     * Tokenizer to find migrations.
     *
     * @var TokenizerInterface
     */
    protected $tokenizer = null;

    /**
     * File component used to save migrations.
     *
     * @var FilesInterface
     */
    protected $files = null;

    /**
     * Target migrator database.
     *
     * @var Database
     */
    protected $database = null;

    /**
     * New Migrator instance, migrators are responsible for migrations running and registering.
     *
     * @param ConfiguratorInterface $configurator
     * @param ContainerInterface    $container
     * @param TokenizerInterface    $tokenizer
     * @param FilesInterface        $files
     */
    public function __construct(
        ConfiguratorInterface $configurator,
        ContainerInterface $container,
        TokenizerInterface $tokenizer,
        FilesInterface $files
    )
    {
        $this->config = $configurator->getConfig($this);

        $this->container = $container;
        $this->tokenizer = $tokenizer;
        $this->files = $files;
    }

    /**
     * Configuring migrator with specific database to work with.
     *
     * @param Database $database
     */
    public function setDatabase(Database $database)
    {
        $this->database = $database;
    }

    /**
     * Check if migrator are set and can be used. Default migrator will check that migrations table
     * exists in associated database.
     *
     * @return bool
     */
    public function isConfigured()
    {
        return $this->migrationsTable()->isExists();
    }

    /**
     * Configure migrator (create tables, files and etc).
     */
    public function configure()
    {
        if ($this->isConfigured())
        {
            return;
        }

        /**
         * Migrations table is pretty simple.
         */
        $schema = $this->migrationsTable()->schema();

        $schema->column('id')->primary();
        $schema->column('migration')->string(255)->index();
        $schema->column('timestamp')->bigInteger();
        $schema->column('timePerformed')->timestamp();

        $schema->save();
    }

    /**
     * Get list of all migrations. Every migration should have status filled.
     *
     * @return MigrationInterface[]
     */
    public function getMigrations()
    {
    }

    /**
     * Used to register new migration by migration class name. Migrator implementation should store
     * migration class in it's storage.
     *
     * Examples:
     * $repository->registerMigration('create_blog_tables', 'Vendor\Blog\Migrations\BlogTables');
     *
     * @param string $name  Migration name.
     * @param string $class Class name to represent migration.
     * @return string
     * @throws MigrationException
     */
    public function registerMigration($name, $class)
    {
    }

    /**
     * Run one outstanding migration, migrations will be performed in an order they were registered.
     */
    public function run()
    {
    }

    /**
     * Rollback last executed migration.
     */
    public function rollback()
    {
    }

    /**
     * Migration table, all migration information will be stored in it.
     *
     * @return Table
     */
    protected function migrationsTable()
    {
        return $this->database->table($this->config['table']);
    }
}