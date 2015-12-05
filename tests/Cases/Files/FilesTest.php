<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Tests\Cases\Files;

use Spiral\Files\FileManager;
use Spiral\Files\FilesInterface;

class FilesTest extends \PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        $filename = sys_get_temp_dir() . '/test.txt';
        $directory = sys_get_temp_dir() . '/abc/cde';

        if (is_file($filename)) {
            unlink($filename);
        }

        if (is_dir($directory)) {
            rmdir($directory);
            rmdir(dirname($directory));
        }
    }

    public function testReadWrite()
    {
        $files = new FileManager();
        $filename = sys_get_temp_dir() . '/test.txt';

        $files->write($filename, 'some data');
        $this->assertEquals('some data', $files->read($filename));
        $this->assertEquals(file_get_contents($filename), $files->read($filename));
    }

    public function testTime()
    {
        $files = new FileManager();
        $filename = sys_get_temp_dir() . '/test.txt';

        $files->write($filename, 'some data', FilesInterface::READONLY);

        $this->assertEquals(filemtime($filename), $files->time($filename));
    }

    public function testMd5()
    {
        $files = new FileManager();
        $filename = sys_get_temp_dir() . '/test.txt';

        $files->write($filename, 'some data');
        $this->assertEquals(md5_file($filename), $files->md5($filename));
    }

    /**
     * @expectedException \Spiral\Files\Exceptions\FileNotFoundException
     */
    public function testExceptions()
    {
        $files = new FileManager();
        $filename = sys_get_temp_dir() . '/test.txt';

        $this->assertFalse($files->exists($filename));

        $files->read($filename);
    }

    public function testDirectoryRuntime()
    {
        $files = new FileManager();
        $directory = sys_get_temp_dir() . '/abc/cde';

        $this->assertFalse($files->isDirectory($directory));
        $this->assertFalse($files->isDirectory(dirname($directory)));

        $files->ensureDirectory($directory);

        $this->assertTrue($files->isDirectory(dirname($directory)));
        $this->assertTrue($files->isDirectory($directory));
    }

    public function testDirectoryReadonly()
    {
        $files = new FileManager();
        $directory = sys_get_temp_dir() . '/abc/cde';

        $this->assertFalse($files->isDirectory($directory));
        $this->assertFalse($files->isDirectory(dirname($directory)));

        $files->ensureDirectory($directory, FilesInterface::READONLY);

        $this->assertTrue($files->isDirectory(dirname($directory)));
        $this->assertTrue($files->isDirectory($directory));
    }
}