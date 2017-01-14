<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ORM;

use Spiral\Core\Container;
use Spiral\Database\Configs\DatabasesConfig;
use Spiral\Database\DatabaseManager;
use Spiral\Database\Entities\Database;
use Spiral\Database\Entities\Driver;
use Spiral\Database\Helpers\SynchronizationPool;
use Spiral\ORM\ORM;
use Spiral\ORM\ORMInterface;
use Spiral\ORM\Schemas\SchemaBuilder;
use Spiral\Tests\Core\Fixtures\SharedComponent;
use Spiral\Tests\ORM\Fixtures\AbstactRecord;
use Spiral\Tests\ORM\Fixtures\Comment;
use Spiral\Tests\ORM\Fixtures\Post;
use Spiral\Tests\ORM\Fixtures\Profile;
use Spiral\Tests\ORM\Fixtures\Tag;
use Spiral\Tests\ORM\Fixtures\User;
use Spiral\Tests\ORM\Traits\ORMTrait;

abstract class BaseTest extends \PHPUnit_Framework_TestCase
{
    use ORMTrait;

    const PROFILING = ENABLE_PROFILING;

    const MODELS = [User::class, Post::class, Comment::class, Tag::class, Profile::class];

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

        $container = new Container();
        $container->bind(ORMInterface::class, $this->orm);

        SharedComponent::shareContainer($container);
    }

    public function tearDown()
    {
        SharedComponent::shareContainer(null);

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

    protected function assertSameInDB(AbstactRecord $record)
    {
        $this->assertTrue($record->isLoaded());
        $this->assertNotEmpty($record->primaryKey());

        $fromDB = $this->orm->source(get_class($record))->findByPK($record->primaryKey());
        $this->assertInstanceOf(get_class($record), $fromDB);

        $this->assertEquals(
            $record->getFields(),
            $fromDB->getFields()
        );
    }

    /**
     * Database driver.
     *
     * @return Driver
     */
    abstract function getDriver(): Driver;
}