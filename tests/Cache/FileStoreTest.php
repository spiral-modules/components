<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Tests\Cache;

use Mockery as m;
use Spiral\Cache\Stores\FileStore;
use Spiral\Files\FilesInterface;

class FileStoreTest extends \PHPUnit_Framework_TestCase
{
    public function testHas()
    {
        $files = m::mock(FilesInterface::class);
        $files->shouldReceive('normalizePath')->with('cache', true)->andReturn('cache/');

        $store = new FileStore($files, 'cache', 'cache');

        $files->shouldReceive('exists')->with('cache/' . md5('test') . '.cache')->andReturn(false);
        $this->assertFalse($store->has('test'));
    }

    public function testHasAndNotExpired()
    {
        $files = m::mock(FilesInterface::class);
        $files->shouldReceive('normalizePath')->with('cache', true)->andReturn('cache/');

        $store = new FileStore($files, 'cache', 'cache');

        $files->shouldReceive('exists')->with('cache/' . md5('test') . '.cache')->andReturn(true);

        $files->shouldReceive('read')->with('cache/' . md5('test') . '.cache')->andReturn(serialize([
            0 => time() + 1000,
            1 => 'data'
        ]));

        $this->assertTrue($store->has('test'));
    }

    public function testHasAndExpired()
    {
        $files = m::mock(FilesInterface::class);
        $files->shouldReceive('normalizePath')->with('cache', true)->andReturn('cache/');

        $store = new FileStore($files, 'cache', 'cache');

        $files->shouldReceive('exists')->with('cache/' . md5('test') . '.cache')->andReturn(true);

        $files->shouldReceive('read')->with('cache/' . md5('test') . '.cache')->andReturn(
            serialize([0 => time() - 1000, 1 => 'data'])
        );

        $files->shouldReceive('delete')->with('cache/' . md5('test') . '.cache');

        $this->assertFalse($store->has('test'));
    }

    public function testGet()
    {
        $files = m::mock(FilesInterface::class);
        $files->shouldReceive('normalizePath')->with('cache', true)->andReturn('cache/');

        $store = new FileStore($files, 'cache', 'cache');

        $files->shouldReceive('exists')->with('cache/' . md5('test') . '.cache')->andReturn(false);

        $this->assertNull($store->get('test'));
    }

    public function testGetAndNotExpired()
    {
        $files = m::mock(FilesInterface::class);
        $files->shouldReceive('normalizePath')->with('cache', true)->andReturn('cache/');

        $store = new FileStore($files, 'cache', 'cache');

        $files->shouldReceive('exists')->with('cache/' . md5('test') . '.cache')->andReturn(true);

        $files->shouldReceive('read')->with('cache/' . md5('test') . '.cache')->andReturn(
            serialize([0 => time() + 1000, 1 => 'data'])
        );

        $this->assertSame('data', $store->get('test'));
    }

    public function testGetAndExpired()
    {
        $files = m::mock(FilesInterface::class);
        $files->shouldReceive('normalizePath')->with('cache', true)->andReturn('cache/');

        $store = new FileStore($files, 'cache', 'cache');

        $files->shouldReceive('exists')->with('cache/' . md5('test') . '.cache')->andReturn(true);

        $files->shouldReceive('read')->with('cache/' . md5('test') . '.cache')->andReturn(
            serialize([0 => time() - 1000, 1 => 'data'])
        );

        $files->shouldReceive('delete')->with('cache/' . md5('test') . '.cache');

        $this->assertNull($store->get('test'));
    }

    public function testSet()
    {
        $files = m::mock(FilesInterface::class);
        $files->shouldReceive('normalizePath')->with('cache', true)->andReturn('cache/');

        $store = new FileStore($files, 'cache', 'cache');

        $files->shouldReceive('write')->with(
            'cache/' . md5('test') . '.cache',
            serialize([0 => time() + 100, 1 => 'data'])
        )->andReturn(true);

        $this->assertTrue($store->set('test', 'data', 100));
    }

    public function testForever()
    {
        $files = m::mock(FilesInterface::class);
        $files->shouldReceive('normalizePath')->with('cache', true)->andReturn('cache/');

        $store = new FileStore($files, 'cache', 'cache');

        $files->shouldReceive('write')->with(
            'cache/' . md5('test') . '.cache',
            serialize([0 => 0, 1 => 'data'])
        )->andReturn(true);

        $this->assertTrue($store->forever('test', 'data'));
    }

    public function testDelete()
    {
        $files = m::mock(FilesInterface::class);
        $files->shouldReceive('normalizePath')->with('cache', true)->andReturn('cache/');

        $store = new FileStore($files, 'cache', 'cache');

        $files->shouldReceive('delete')->with('cache/' . md5('test') . '.cache');
        $store->delete('test');
    }

    public function testFlush()
    {
        $files = m::mock(FilesInterface::class);
        $files->shouldReceive('normalizePath')->with('cache', true)->andReturn('cache/');

        $store = new FileStore($files, 'cache', 'cache');

        $files->shouldReceive('getFiles')->with('cache/', 'cache')->andReturn(['abc']);
        $files->shouldReceive('delete')->with('abc');

        $store->clear();
    }

    public function testIncForever()
    {
        $files = m::mock(FilesInterface::class);
        $files->shouldReceive('normalizePath')->with('cache', true)->andReturn('cache/');

        $store = m::mock(FileStore::class . '[get,forever,set]', [$files, 'cache', 'cache']);

        $store->shouldReceive('get')->with(
            'abc',
            m::on(function (&$expiration) {
                $expiration = null;

                return true;
            })
        )->andReturn(null);

        $store->shouldReceive('forever')->with('abc', 1);

        $this->assertSame(1, $store->inc('abc'));
    }

    public function testIncForeverWithValue()
    {
        $files = m::mock(FilesInterface::class);
        $files->shouldReceive('normalizePath')->with('cache', true)->andReturn('cache/');

        $store = m::mock(FileStore::class . '[get,forever,set]', [$files, 'cache', 'cache']);

        $store->shouldReceive('get')->with(
            'abc',
            m::on(function (&$expiration) {
                $expiration = 0;

                return true;
            })
        )->andReturn(1);

        $store->shouldReceive('forever')->with('abc', 2);

        $this->assertSame(2, $store->inc('abc'));
    }

    public function testIncExpirationWithValue()
    {
        $files = m::mock(FilesInterface::class);
        $files->shouldReceive('normalizePath')->with('cache', true)->andReturn('cache/');

        $store = m::mock(FileStore::class . '[get,forever,set]', [$files, 'cache', 'cache']);

        $store->shouldReceive('get')->with(
            'abc',
            m::on(function (&$expiration) {
                $expiration = time() + 1000;

                return true;
            })
        )->andReturn(10);

        $store->shouldReceive('set')->with('abc', 11, 1000);

        $this->assertSame(11, $store->inc('abc'));
    }

    public function testDecForever()
    {
        $files = m::mock(FilesInterface::class);
        $files->shouldReceive('normalizePath')->with('cache', true)->andReturn('cache/');

        $store = m::mock(FileStore::class . '[get,forever,set]', [$files, 'cache', 'cache']);

        $store->shouldReceive('get')->with(
            'abc',
            m::on(function (&$expiration) {
                $expiration = null;

                return true;
            })
        )->andReturn(null);

        $store->shouldReceive('forever')->with('abc', -1);

        $this->assertSame(-1, $store->dec('abc'));
    }

    public function testDecForeverWithValue()
    {
        $files = m::mock(FilesInterface::class);
        $files->shouldReceive('normalizePath')->with('cache', true)->andReturn('cache/');

        $store = m::mock(FileStore::class . '[get,forever,set]', [$files, 'cache', 'cache']);

        $store->shouldReceive('get')->with(
            'abc',
            m::on(function (&$expiration) {
                $expiration = 0;

                return true;
            })
        )->andReturn(2);

        $store->shouldReceive('forever')->with('abc', 1);

        $this->assertSame(1, $store->dec('abc'));
    }

    public function testDecExpirationWithValue()
    {
        $files = m::mock(FilesInterface::class);
        $files->shouldReceive('normalizePath')->with('cache', true)->andReturn('cache/');

        $store = m::mock(FileStore::class . '[get,forever,set]', [$files, 'cache', 'cache']);

        $store->shouldReceive('get')->with(
            'abc',
            m::on(function (&$expiration) {
                $expiration = time() + 1000;

                return true;
            })
        )->andReturn(10);

        $store->shouldReceive('set')->with('abc', 9, 1000);

        $this->assertSame(9, $store->dec('abc'));
    }
}