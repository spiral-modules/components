<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Tests\Cache;

use Spiral\Cache\Configs\CacheConfig;
use Spiral\Cache\Stores\APCStore;
use Spiral\Cache\Stores\FileStore;
use Spiral\Cache\Stores\MemcacheStore;

class ConfigTest extends \PHPUnit_Framework_TestCase
{
    public function testSection()
    {
        $this->assertSame('cache', CacheConfig::CONFIG);
    }

    public function testDefaultStore()
    {
        $config = new CacheConfig([
            'store' => 'redis'
        ]);

        $this->assertSame('redis', $config->defaultStore());
    }

    public function testHasStore()
    {
        $config = new CacheConfig([
            'store'  => 'redis',
            'stores' => [
                'redis' => [],
                'file'  => []
            ]
        ]);

        $this->assertTrue($config->hasStore('redis'));
        $this->assertTrue($config->hasStore('file'));
        $this->assertFalse($config->hasStore('memcache'));
    }

    public function testStoreClass()
    {
        $config = new CacheConfig([
            'store'  => 'file',
            'stores' => [
                'file' => [
                    'class' => FileStore::class
                ]
            ]
        ]);

        $this->assertTrue($config->hasStore('file'));
        $this->assertSame(FileStore::class, $config->storeClass('file'));
    }

    public function testStoreOptions()
    {
        $config = new CacheConfig([
            'store'  => 'file',
            'stores' => [
                'file'     => [
                    'class' => FileStore::class
                ],
                'memcache' => [
                    'class'   => MemcacheStore::class,
                    'servers' => ['a', 'b', 'c']
                ],
                'redis'    => [
                    'options' => [
                        'server' => 'abc'
                    ]
                ]
            ]
        ]);

        $this->assertTrue($config->hasStore('file'));
        $this->assertSame([], $config->storeOptions('file'));

        $this->assertTrue($config->hasStore('memcache'));
        $this->assertSame(['servers' => ['a', 'b', 'c']], $config->storeOptions('memcache'));


        $this->assertTrue($config->hasStore('redis'));
        $this->assertSame(['server' => 'abc'], $config->storeOptions('redis'));
    }

    public function testResolveStore()
    {
        $config = new CacheConfig([
            'store'  => 'file',
            'stores' => [
                'file'     => [
                    'class' => FileStore::class
                ],
                'memcache' => [
                    'class'   => MemcacheStore::class,
                    'servers' => ['a', 'b', 'c']
                ]
            ]
        ]);

        $this->assertSame('file', $config->resolveStore(
            new \ReflectionClass(FileStore::class)
        ));

        $this->assertSame('memcache', $config->resolveStore(
            new \ReflectionClass(MemcacheStore::class)
        ));
    }

    /**
     * @expectedException \Spiral\Cache\Exceptions\ConfigException
     * @expectedExceptionMessage Unable to detect store options for cache store
     *                           'Spiral\Cache\Stores\APCStore'
     */
    public function testResolveInvalidStore()
    {
        $config = new CacheConfig([
            'store'  => 'file',
            'stores' => [
                'file'     => [
                    'class' => FileStore::class
                ],
                'memcache' => [
                    'class'   => MemcacheStore::class,
                    'servers' => ['a', 'b', 'c']
                ]
            ]
        ]);

        $this->assertSame('apc', $config->resolveStore(
            new \ReflectionClass(APCStore::class)
        ));
    }
}