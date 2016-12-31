<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\Storage\Servers;

use Psr\Http\Message\StreamInterface;
use Spiral\Storage\BucketInterface;
use Spiral\Storage\ServerInterface;

abstract class OperationsTest extends \PHPUnit_Framework_TestCase
{
    const PROFILING = true;

    protected $skipped = false;

    public function tearDown()
    {
        if ($this->skipped) {
            return;
        }

        $this->getBucket()->exists('target') && $this->getBucket()->delete('target');
        $this->getBucket()->exists('targetB') && $this->getBucket()->delete('targetB');
        $this->getBucket()->exists('targetDir/targetName') && $this->getBucket()->delete('targetDir/targetName');
    }

    /**
     * @expectedException \Spiral\Storage\Exceptions\ServerException
     * @expectedExceptionMessage Source must be a valid resource, stream or filename, invalid value
     *                           given
     */
    public function testPutString()
    {
        $bucket = $this->getBucket();

        $this->assertFalse($bucket->exists('target'));

        $content = random_bytes(mt_rand(100, 100000));
        $bucket->put('target', $content);
        $this->assertTrue($bucket->exists('target'));
    }

    public function testPutStream()
    {
        $bucket = $this->getBucket();

        $this->assertFalse($bucket->exists('target'));

        $content = $this->getStreamSource();
        $bucket->put('target', $content);

        $this->assertTrue($bucket->exists('target'));
    }

    public function testPutStreamLongName()
    {
        $bucket = $this->getBucket();

        $this->assertFalse($bucket->exists('target'));

        $content = $this->getStreamSource();
        $bucket->put('targetDir/targetName', $content);

        $this->assertTrue($bucket->exists('targetDir/targetName'));
    }

    public function testPutFilename()
    {
        $bucket = $this->getBucket();

        $this->assertFalse($bucket->exists('target'));

        $content = __FILE__;
        $bucket->put('target', $content);

        $this->assertTrue($bucket->exists('target'));
    }

    public function testPutResource()
    {
        $bucket = $this->getBucket();

        $this->assertFalse($bucket->exists('target'));

        $content = fopen(__FILE__, 'rb');
        $bucket->put('target', $content);

        $this->assertTrue($bucket->exists('target'));
    }

    public function testAddress()
    {
        $bucket = $this->getBucket();

        $this->assertFalse($bucket->exists('target'));

        $content = $this->getStreamSource();
        $address = $bucket->put('target', $content);

        $this->assertNotNull($address);
        $this->assertSame($bucket->getPrefix() . 'target', $address);
    }

    public function testStreamIntegrity()
    {
        $bucket = $this->getBucket();

        $this->assertFalse($bucket->exists('target'));

        $content = $this->getStreamSource();
        $bucket->put('target', $content);

        $content->rewind();

        $stream = $bucket->allocateStream('target');
        $this->assertSame($content->getContents(), $stream->getContents());
    }

    public function testResourceIntegrity()
    {
        $bucket = $this->getBucket();

        $this->assertFalse($bucket->exists('target'));

        $content = fopen(__FILE__, 'rb');
        $bucket->put('target', $content);

        $stream = $bucket->allocateStream('target');
        $this->assertSame(file_get_contents(__FILE__), $stream->getContents());
    }

    public function testFilenameIntegrity()
    {
        $bucket = $this->getBucket();

        $this->assertFalse($bucket->exists('target'));

        $content = __FILE__;
        $bucket->put('target', $content);

        $stream = $bucket->allocateStream('target');
        $this->assertSame(file_get_contents(__FILE__), $stream->getContents());
    }

    public function testSize()
    {
        $bucket = $this->getBucket();
        $this->assertFalse($bucket->exists('target'));

        $content = $this->getStreamSource();
        $bucket->put('target', $content);

        $this->assertTrue($bucket->exists('target'));

        $this->assertSame($content->getSize(), $bucket->size('target'));
    }

    public function testSizeNull()
    {
        $bucket = $this->getBucket();
        $this->assertSame(null, $bucket->size('target'));
    }

    public function testLocalFilename()
    {
        $bucket = $this->getBucket();
        $this->assertFalse($bucket->exists('target'));

        $content = $this->getStreamSource();

        $bucket->put('target', $content);
        $this->assertTrue($bucket->exists('target'));

        $localFilename = $bucket->allocateFilename('target');

        $this->assertNotEmpty($localFilename);
        $this->assertTrue(file_exists($localFilename));

        //Written!
        $content->rewind();

        $this->assertSame($content->getContents(), file_get_contents($localFilename));
    }

    public function testLocalStream()
    {
        $bucket = $this->getBucket();
        $this->assertFalse($bucket->exists('target'));

        $content = $this->getStreamSource();

        $bucket->put('target', $content);
        $this->assertTrue($bucket->exists('target'));

        $stream = $bucket->allocateStream('target');
        $this->assertInstanceOf(StreamInterface::class, $stream);

        //Written!
        $content->rewind();

        $this->assertSame($content->getSize(), $stream->getSize());
        $this->assertSame($content->getContents(), $stream->getContents());
    }

    public function testRename()
    {
        $bucket = $this->getBucket();
        $this->assertFalse($bucket->exists('target'));

        $content = $this->getStreamSource();

        $bucket->put('target', $content);
        $this->assertTrue($bucket->exists('target'));

        $newAddress = $bucket->rename('target', 'targetB');

        $this->assertNotNull($newAddress);
        $this->assertSame($bucket->getPrefix() . 'targetB', $newAddress);

        $this->assertFalse($bucket->exists('target'));
        $this->assertTrue($bucket->exists('targetB'));

        $stream = $bucket->allocateStream('targetB');
        $this->assertInstanceOf(StreamInterface::class, $stream);

        //Written!
        $content->rewind();

        $this->assertSame($content->getSize(), $stream->getSize());
        $this->assertSame($content->getContents(), $stream->getContents());
    }

    public function testRenameLongName()
    {
        $bucket = $this->getBucket();
        $this->assertFalse($bucket->exists('target'));

        $content = $this->getStreamSource();

        $bucket->put('target', $content);
        $this->assertTrue($bucket->exists('target'));

        $newAddress = $bucket->rename('target', 'targetDir/targetName');

        $this->assertNotNull($newAddress);
        $this->assertSame($bucket->getPrefix() . 'targetDir/targetName', $newAddress);

        $this->assertFalse($bucket->exists('target'));
        $this->assertTrue($bucket->exists('targetDir/targetName'));

        $stream = $bucket->allocateStream('targetDir/targetName');
        $this->assertInstanceOf(StreamInterface::class, $stream);

        //Written!
        $content->rewind();

        $this->assertSame($content->getSize(), $stream->getSize());
        $this->assertSame($content->getContents(), $stream->getContents());
    }

    public function testDelete()
    {
        $bucket = $this->getBucket();

        $this->assertFalse($bucket->exists('target'));

        $content = $this->getStreamSource();

        $bucket->put('target', $content);
        $this->assertTrue($bucket->exists('target'));

        $bucket->delete('target');
        $this->assertFalse($bucket->exists('target'));
    }

    protected function getStreamSource(): StreamInterface
    {
        $content = random_bytes(mt_rand(100, 100000));

        return \GuzzleHttp\Psr7\stream_for($content);
    }

    abstract protected function getBucket(): BucketInterface;

    abstract protected function getServer(): ServerInterface;
}