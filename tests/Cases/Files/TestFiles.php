<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Tests\Cases\Files;

use Spiral\Files\FileManager;

class TestFiles extends \PHPUnit_Framework_TestCase
{
    public function testWriteReadSimple()
    {
        $files = new FileManager();
        $filename = sys_get_temp_dir() . '/test.txt';

        $files->write($filename, 'some data');
        $this->assertEquals('some data', $files->read($filename));
        $this->assertEquals(file_get_contents($filename), $files->read($filename));
    }

    //todo: keep writing
}