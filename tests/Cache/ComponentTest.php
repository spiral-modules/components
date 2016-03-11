<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Tests\Cache;

use Mockery as m;
use Spiral\Cache\CacheManager;
use Spiral\Cache\Configs\CacheConfig;
use Spiral\Cache\Stores\FileStore;
use Spiral\Core\FactoryInterface;

class ComponentTest extends \PHPUnit_Framework_TestCase
{
    public function testDefaultStore()
    {
        $config = m::mock(CacheConfig::class);
        $factory = m::mock(FactoryInterface::class);
        $store = m::mock(FileStore::class);

        $manager = new CacheManager($config, $factory);

        $config->shouldReceive('defaultStore')->andReturn('file');
        $config->shouldReceive('resolveAlias')->with('file')->andReturn('file');

        $config->shouldReceive('storeClass')->with('file')->andReturn(FileStore::class);
        $config->shouldReceive('storeOptions')->with('file')->andReturn([]);

        $factory->shouldReceive('make')->with(FileStore::class, [])->andReturn($store);

        $store->shouldReceive('isAvailable')->andReturn(true);

        $this->assertSame($store, $manager->getStore());
    }

    /**
     * @expectedException \Spiral\Cache\Exceptions\CacheException
     * @expectedExceptionMessage Unable to use default store 'file', driver is unavailable
     */
    public function testDefaultStoreUnavailable()
    {
        $config = m::mock(CacheConfig::class);
        $factory = m::mock(FactoryInterface::class);
        $store = m::mock(FileStore::class);

        $manager = new CacheManager($config, $factory);

        $config->shouldReceive('defaultStore')->andReturn('file');
        $config->shouldReceive('resolveAlias')->with('file')->andReturn('file');

        $config->shouldReceive('storeClass')->with('file')->andReturn(FileStore::class);
        $config->shouldReceive('storeOptions')->with('file')->andReturn([]);

        $factory->shouldReceive('make')->with(FileStore::class, [])->andReturn($store);

        $store->shouldReceive('isAvailable')->andReturn(false);

        $this->assertSame($store, $manager->getStore());
    }

    public function testStoreWithName()
    {
        $config = m::mock(CacheConfig::class);
        $factory = m::mock(FactoryInterface::class);
        $store = m::mock(FileStore::class);

        $manager = new CacheManager($config, $factory);

        $config->shouldReceive('resolveAlias')->with('file')->andReturn('file');

        $config->shouldReceive('storeClass')->with('file')->andReturn(FileStore::class);
        $config->shouldReceive('storeOptions')->with('file')->andReturn([]);

        $factory->shouldReceive('make')->with(FileStore::class, [])->andReturn($store);

        $config->shouldReceive('defaultStore')->andReturn('file');
        $store->shouldReceive('isAvailable')->andReturn(true);

        $this->assertSame($store, $manager->getStore('file'));
    }

    public function testInjection()
    {
        $config = m::mock(CacheConfig::class);
        $factory = m::mock(FactoryInterface::class);
        $store = m::mock(FileStore::class);

        $manager = new CacheManager($config, $factory);

        $config->shouldReceive('resolveStore')->with(
            m::on(function (\ReflectionClass $reflection) {
                return $reflection->getName() == FileStore::class;
            })
        )->andReturn(true);
        $config->shouldReceive('resolveAlias')->with('file')->andReturn('file');

        $config->shouldReceive('storeClass')->with('file')->andReturn(FileStore::class);
        $config->shouldReceive('storeOptions')->with('file')->andReturn([]);

        $factory->shouldReceive('make')->with(FileStore::class, [])->andReturn($store);

        $config->shouldReceive('defaultStore')->andReturn('file');
        $store->shouldReceive('isAvailable')->andReturn(true);

        $this->assertSame($store, $manager->createInjection(
            new \ReflectionClass(FileStore::class)
        ));
    }
}