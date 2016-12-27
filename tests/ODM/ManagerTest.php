<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ODM;

use Mockery as m;
use MongoDB\Driver\Manager;
use Spiral\Core\FactoryInterface;
use Spiral\ODM\Configs\MongoConfig;
use Spiral\ODM\Entities\MongoDatabase;
use Spiral\ODM\MongoManager;

class ManagerTest extends \PHPUnit_Framework_TestCase
{
    public function testDefaultDatabase()
    {
        $config = m::mock(MongoConfig::class);
        $factory = m::mock(FactoryInterface::class);
        $db = m::mock(MongoDatabase::class);

        $manager = new MongoManager($config, $factory);

        $config->shouldReceive('defaultDatabase')->andReturn('default');
        $config->shouldReceive('resolveAlias')->with('default')->andReturn('default');
        $config->shouldReceive('hasDatabase')->with('default')->andReturn(true);
        $config->shouldReceive('databaseOptions')->with('default')->andReturn([
            'server'   => 'mongodb://localhost:27017',
            'database' => 'spiral-empty',
            'options'  => ['connect' => true]
        ]);

        $factory->shouldReceive('make')->with(MongoDatabase::class, [
            'databaseName' => 'spiral-empty',
            'manager'      => new Manager('mongodb://localhost:27017', ['connect' => true]),
            'options'      => ['connect' => true]
        ])->andReturn($db);

        $this->assertSame($db, $manager->database());
    }

    public function testNamedDatabase()
    {
        $config = m::mock(MongoConfig::class);
        $factory = m::mock(FactoryInterface::class);
        $db = m::mock(MongoDatabase::class);

        $manager = new MongoManager($config, $factory);

        $config->shouldNotReceive('defaultDatabase');
        $config->shouldReceive('resolveAlias')->with('named')->andReturn('default');
        $config->shouldReceive('hasDatabase')->with('default')->andReturn(true);
        $config->shouldReceive('databaseOptions')->with('default')->andReturn([
            'server'   => 'mongodb://localhost:27017',
            'database' => 'spiral-empty',
            'options'  => ['connect' => true]
        ]);

        $factory->shouldReceive('make')->with(MongoDatabase::class, [
            'databaseName' => 'spiral-empty',
            'manager'      => new Manager('mongodb://localhost:27017', ['connect' => true]),
            'options'      => ['connect' => true]
        ])->andReturn($db);

        $this->assertSame($db, $manager->database('named'));
    }

    /**
     * @expectedException \Spiral\ODM\Exceptions\ODMException
     * @expectedExceptionMessage Unable to initiate MongoDatabase, no presets for 'db' found
     */
    public function testNoDatabase()
    {
        $config = m::mock(MongoConfig::class);
        $factory = m::mock(FactoryInterface::class);

        $manager = new MongoManager($config, $factory);

        $config->shouldNotReceive('defaultDatabase');
        $config->shouldReceive('resolveAlias')->with('named')->andReturn('db');
        $config->shouldReceive('hasDatabase')->with('db')->andReturn(false);

        $manager->database('named');
    }

    public function testGetDatabases()
    {
        $config = m::mock(MongoConfig::class);
        $factory = m::mock(FactoryInterface::class);
        $db = m::mock(MongoDatabase::class);

        $manager = new MongoManager($config, $factory);

        $config->shouldReceive('databaseNames')->andReturn(['default']);

        $config->shouldNotReceive('defaultDatabase');
        $config->shouldReceive('resolveAlias')->with('default')->andReturn('default');
        $config->shouldReceive('hasDatabase')->with('default')->andReturn(true);
        $config->shouldReceive('databaseOptions')->with('default')->andReturn([
            'server'   => 'mongodb://localhost:27017',
            'database' => 'spiral-empty',
            'options'  => ['connect' => true]
        ]);

        $factory->shouldReceive('make')->with(MongoDatabase::class, [
            'databaseName' => 'spiral-empty',
            'manager'      => new Manager('mongodb://localhost:27017', ['connect' => true]),
            'options'      => ['connect' => true]
        ])->andReturn($db);

        $this->assertSame([$db], $manager->getDatabases());
    }
}