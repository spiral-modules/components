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
use Spiral\Database\DatabaseManager;
use Spiral\Database\Entities\Database;
use Spiral\Database\Entities\Table;
use Spiral\Database\Exceptions\MigrationException;
use Spiral\Debug\Traits\LoggerTrait;
use Spiral\Files\FilesInterface;
use Spiral\Tokenizer\Reflections\ReflectionFile;
use Spiral\Tokenizer\TokenizerInterface;

/**
 * Default implementation of spiral migration can be configured via ConfiguratorInterface and uses
 * associated database table to store migration status.
 */
class Migrator extends Component implements MigratorInterface, LoggerAwareInterface
{
    /**
     * Can be configured, plus some operations will raise log messages.
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
     * Target migrator database.
     *
     * @var Database
     */
    private $database = null;

    /**
     * Used to solve problems when multiple migrations added at one.
     *
     * @var int
     */
    private $chunkID = 1;

    /**
     * @invisible
     * @var ContainerInterface
     */
    protected $container = null;

    /**
     * @invisible
     * @var TokenizerInterface
     */
    protected $tokenizer = null;

    /**
     * @invisible
     * @var FilesInterface
     */
    protected $files = null;

    /**
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
     * {@inheritdoc}
     */
    public function setDatabase(Database $database)
    {
        $this->database = $database;
    }

    /**
     * {@inheritdoc}
     */
    public function isConfigured()
    {
        return $this->migrationsTable()->exists();
    }

    /**
     * {@inheritdoc}
     */
    public function configure()
    {
        if ($this->isConfigured())
        {
            return;
        }

        //Migrations table is pretty simple.
        $schema = $this->migrationsTable()->schema();

        $schema->column('id')->primary();
        $schema->column('migration')->string(255)->index();
        $schema->column('timePerformed')->datetime();

        $schema->save();
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
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
     * {@inheritdoc}
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
     * {@inheritdoc}
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

    /**
     * Internal method to fetch all migration filenames.
     *
     * @return array
     */
    private function getFiles()
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
    private function getStatus(array $definition)
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
     * Request new migration filename based on user input and current timestamp.
     *
     * @param string $name
     * @return string
     */
    private function createFilename($name)
    {
        $name = Inflector::tableize($name);

        $filename = \Spiral\interpolate(self::FILENAME_FORMAT, [
            'timestamp' => date(self::TIMESTAMP_FORMAT),
            'chunk'     => $this->chunkID++,
            'name'      => $name
        ]);

        return $this->files->normalizePath($this->config['directory'] . '/' . $filename);
    }
}