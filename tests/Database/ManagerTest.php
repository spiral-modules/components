<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\tests\Cases\Database;

use Mockery as m;
use Spiral\Core\FactoryInterface;
use Spiral\Database\Configs\DatabasesConfig;
use Spiral\Database\DatabaseManager;
use Spiral\Database\Drivers\SQLite\SQLiteDriver;
use Spiral\Database\Entities\Database;

class ManagerTest extends \PHPUnit_Framework_TestCase
{
    const DEFAULT_OPTIONS = [
        'connection' => 'sqlite:' . __DIR__ . 'Drivers/SQLite/fixture/runtime.db',
        'username'   => 'sqlite',
        'password'   => '',
        'options'    => []
    ];

    public function testDefaultDatabase()
    {
        $config = m::mock(DatabasesConfig::class);
        $factory = m::mock(FactoryInterface::class);
        $db = m::mock(Database::class);

        $manager = new DatabaseManager($config, $factory);

        $config->shouldReceive('defaultDatabase')->andReturn('default');
        $config->shouldReceive('resolveAlias')->with('default')->andReturn('default');
        $config->shouldReceive('hasDatabase')->with('default')->andReturn(true);

        $config->shouldReceive('databasePrefix')->with('default')->andReturn('prefix');
        $config->shouldReceive('databaseDriver')->with('default')->andReturn('driverName');

        $config->shouldReceive('hasDriver')->with('driverName')->andReturn(true);
        $config->shouldReceive('driverClass')->with('driverName')->andReturn(SQLiteDriver::class);

        $config->shouldReceive('driverOptions')->with('driverName')->andReturn(self::DEFAULT_OPTIONS);

        $factory->shouldReceive('make')->with(SQLiteDriver::class, [
            'name'    => 'driverName',
            'options' => self::DEFAULT_OPTIONS
        ])->andReturn($driver = new SQLiteDriver('driverName', self::DEFAULT_OPTIONS));

        $factory->shouldReceive('make')->with(Database::class, [
            'name'   => 'default',
            'prefix' => 'prefix',
            'driver' => $driver
        ])->andReturn($db);

        $this->assertSame($db, $manager->database());
    }

    public function testNamedDatabase()
    {
        $config = m::mock(DatabasesConfig::class);
        $factory = m::mock(FactoryInterface::class);
        $db = m::mock(Database::class);

        $manager = new DatabaseManager($config, $factory);

        $config->shouldReceive('resolveAlias')->with('test')->andReturn('default');
        $config->shouldReceive('hasDatabase')->with('default')->andReturn(true);

        $config->shouldReceive('databasePrefix')->with('default')->andReturn('prefix');
        $config->shouldReceive('databaseDriver')->with('default')->andReturn('driverName');

        $config->shouldReceive('hasDriver')->with('driverName')->andReturn(true);
        $config->shouldReceive('driverClass')->with('driverName')->andReturn(SQLiteDriver::class);

        $config->shouldReceive('driverOptions')->with('driverName')->andReturn(self::DEFAULT_OPTIONS);

        $factory->shouldReceive('make')->with(SQLiteDriver::class, [
            'name'    => 'driverName',
            'options' => self::DEFAULT_OPTIONS
        ])->andReturn($driver = new SQLiteDriver('driverName', self::DEFAULT_OPTIONS));

        $factory->shouldReceive('make')->with(Database::class, [
            'name'   => 'default',
            'prefix' => 'prefix',
            'driver' => $driver
        ])->andReturn($db);

        $this->assertSame($db, $manager->database('test'));
    }

    /**
     * @expectedException \Spiral\Database\Exceptions\DBALException
     * @expectedExceptionMessage Unable to create Database, no presets for 'test' found
     */
    public function testNoDatabase()
    {
        $config = m::mock(DatabasesConfig::class);
        $factory = m::mock(FactoryInterface::class);
        $db = m::mock(Database::class);

        $manager = new DatabaseManager($config, $factory);

        $config->shouldReceive('resolveAlias')->with('test')->andReturn('test');
        $config->shouldReceive('hasDatabase')->with('test')->andReturn(false);

        $this->assertSame($db, $manager->database('test'));
    }

    //todo: possibly add few more tests
}