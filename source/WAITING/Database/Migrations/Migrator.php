<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Migrations;

use Doctrine\Common\Inflector\Inflector;
use Psr\Log\LoggerAwareInterface;
use Spiral\Core\Component;
use Spiral\Core\ConfiguratorInterface;
use Spiral\Core\ContainerInterface;
use Spiral\Core\Traits\ConfigurableTrait;
use Spiral\Database\Database;
use Spiral\Database\DatabaseManager;
use Spiral\Database\Table;
use Spiral\Debug\Traits\LoggerTrait;
use Spiral\Files\FilesInterface;
use Spiral\Tokenizer\Reflections\ReflectionFile;
use Spiral\Tokenizer\TokenizerInterface;

class Migrator extends Component implements MigratorInterface, LoggerAwareInterface
{
    /**
     * Configuration.
     */
    use ConfigurableTrait, LoggerTrait;

    /**
     * Configuration section.
     */
    const CONFIG = 'migrations';

    /**
     * Migrations file name format. This format will be used when requesting new migration filename.
     */
    const FILENAME_FORMAT = '{timestamp}_{chunk}_{name}.php';

    /**
     * Timestamp format for files.
     */
    const TIMESTAMP_FORMAT = 'Ymd_His';

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
     * Used to solve problems when multiple migrations added at one.
     *
     * @var int
     */
    protected $chunkID = 1;

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
        $this->config = $configurator->getConfig(static::CONFIG);

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
        $schema->column('timePerformed')->datetime();

        $schema->save();
    }

    /**
     * Get list of all migrations. Every migration should have status filled.
     *
     * @return MigrationInterface[]
     */
    public function getMigrations()
    {
        $migrations = [];

        foreach ($this->getFiles() as $filename => $definition)
        {
            if (!class_exists($definition['class'], false))
            {
                //Can happen sometimes
                require_once($filename);
            }
            else
            {
                $this->logger()->warning(
                    "Migration '{class}' already presented in loaded classes.",
                    $definition
                );

                continue;
            }

            /**
             * @var MigrationInterface $migration
             */
            $migration = $this->container->get($definition['class']);

            //Status
            $migration->setStatus($this->getStatus($definition));

            //Active database
            $migration->setDatabase($this->database);

            $migrations[$filename] = $migration;
        }

        return $migrations;
    }

    /**
     * Internal method to fetch all migration filenames.
     *
     * @return array
     */
    protected function getFiles()
    {
        $filenames = [];

        foreach ($this->files->getFiles($this->config['directory'], 'php') as $filename)
        {
            $reflection = new ReflectionFile($filename, $this->tokenizer);

            $definition = explode('_', $filename);
            $filenames[$filename] = [
                'class'   => $reflection->getClasses()[0],
                'created' => \DateTime::createFromFormat(self::TIMESTAMP_FORMAT, $definition[0]),
                'name'    => str_replace('.php', '', join('_', array_slice($definition, 2)))
            ];
        }

        return $filenames;
    }

    /**
     * Create migration status based on definition.
     *
     * @param array $definition
     * @return StatusInterface
     */
    protected function getStatus(array $definition)
    {
        //Fetch migration information from database
        $migration = $this->migrationsTable()->where([
            'migration' => $definition['name']
        ])->select('id', 'timePerformed')->run()->fetch();

        if (empty($migration['timePerformed']))
        {
            return new Status(
                $definition['name'],
                StatusInterface::PENDING,
                $definition['created'],
                null
            );
        }

        return new Status(
            $definition['name'],
            StatusInterface::EXECUTED,
            $definition['created'],
            new \DateTime(
                $migration['timePerformed'],
                new \DateTimeZone(DatabaseManager::DEFAULT_TIMEZONE)
            )
        );
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
     * @return bool|string
     * @throws MigrationException
     */
    public function registerMigration($name, $class)
    {
        if (!class_exists($class))
        {
            throw new MigrationException(
                "Unable to register migration, representing class does not exists."
            );
        }

        foreach ($this->getMigrations() as $migration)
        {
            if (get_class($migration) == $class)
            {
                //Already presented
                return false;
            }
        }

        //Copying
        $this->files->write(
            $filename = $this->createFilename($name),
            $this->files->read((new \ReflectionClass($class))->getFileName())
        );

        return basename($filename);
    }

    /**
     * Request new migration filename based on user input and current timestamp.
     *
     * @param string $name
     * @return string
     */
    public function createFilename($name)
    {
        $name = Inflector::tableize($name);

        $filename = \Spiral\interpolate(self::FILENAME_FORMAT, [
            'timestamp' => date(self::TIMESTAMP_FORMAT),
            'chunk'     => $this->chunkID++,
            'name'      => $name
        ]);

        return $this->files->normalizePath($this->config['directory'] . '/' . $filename);
    }

    /**
     * Run one outstanding migration, migrations will be performed in an order they were registered.
     * Method must return executed migration.
     *
     * @return MigrationInterface|null
     */
    public function run()
    {
        foreach ($this->getMigrations() as $migration)
        {
            if ($migration->getStatus()->getState() == StatusInterface::PENDING)
            {
                //Yey!
                $migration->up();

                //Registering record in database
                $this->migrationsTable()->insert([
                    'migration'     => $migration->getStatus()->getName(),
                    'timePerformed' => new \DateTime('now')
                ]);

                return $migration;
            }
        }

        return null;
    }

    /**
     * Rollback last executed migration. Method must return rolled back migration.
     *
     * @return MigrationInterface|null
     */
    public function rollback()
    {
        /**
         * @var MigrationInterface $migration
         */
        foreach (array_reverse($this->getMigrations()) as $migration)
        {
            if ($migration->getStatus()->getState() == StatusInterface::EXECUTED)
            {
                //Rolling back
                $migration->down();

                //Flushing DB record
                $this->migrationsTable()->delete([
                    'name' => $migration->getStatus()->getName()
                ])->run();

                return $migration;
            }
        }

        return null;
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