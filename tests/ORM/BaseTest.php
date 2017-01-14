<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ORM;

use Spiral\Database\Configs\DatabasesConfig;
use Spiral\Database\DatabaseManager;
use Spiral\Database\Entities\Database;
use Spiral\Database\Entities\Driver;
use Spiral\Database\Helpers\SynchronizationPool;
use Spiral\ORM\ORM;
use Spiral\ORM\Schemas\SchemaBuilder;
use Spiral\Tests\ORM\Fixtures\Comment;
use Spiral\Tests\ORM\Fixtures\Post;
use Spiral\Tests\ORM\Fixtures\Tag;
use Spiral\Tests\ORM\Fixtures\User;
use Spiral\Tests\ORM\Traits\ORMTrait;

abstract class BaseTest extends \PHPUnit_Framework_TestCase
{
    use ORMTrait;

    const PROFILING = ENABLE_PROFILING;

    const MODELS = [User::class, Post::class, Comment::class, Tag::class];

    /**
     * @var DatabaseManager
     */
    protected $dbal;

    /**
     * @var SchemaBuilder
     */
    protected $builder;

    /**
     * @var ORM
     */
    protected $orm;

    public function setUp()
    {
        $this->dbal = $this->databaseManager();
        $this->builder = $this->makeBuilder($this->dbal);

        $this->orm = new ORM($this->dbal, $this->relationsConfig());

        foreach (static::MODELS as $model) {
            $this->builder->addSchema($this->makeSchema($model));
        }

        $this->builder->renderSchema();
        $this->builder->pushSchema();

        $this->orm->buildSchema($this->builder);
    }

    public function tearDown()
    {
        $schemas = [];
        //Clean up
        foreach ($this->dbal->database()->getTables() as $table) {
            $schema = $table->getSchema();
            $schema->declareDropped();
            $schemas[] = $schema;
        }

        //Clear all tables
        $syncBus = new SynchronizationPool($schemas);
        $syncBus->run();
    }

    /**
     * Default SQLite database.
     *
     * @return DatabaseManager
     */
    protected function databaseManager(): DatabaseManager
    {
        $dbal = new DatabaseManager(new DatabasesConfig([
            'default'     => 'default',
            'aliases'     => [],
            'databases'   => [],
            'connections' => []
        ]));

        $dbal->addDatabase(new Database($this->getDriver(), 'default', ''));
        $dbal->addDatabase(new Database($this->getDriver(), 'slave', 'slave_'));

        return $dbal;
    }

    /**
     * Database driver.
     *
     * @return Driver
     */
    abstract function getDriver(): Driver;
}