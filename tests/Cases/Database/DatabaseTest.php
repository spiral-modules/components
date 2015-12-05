<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Tests\Cases\Database;

use Spiral\Database\Entities\Database;
use Spiral\Database\Entities\Driver;

class DatabaseTest extends \PHPUnit_Framework_TestCase
{
    public function testDatabase()
    {
        /**
         * @var Driver|\PHPUnit_Framework_MockObject_MockObject $driver
         */
        $driver = $this->getMockBuilder(Driver::class)
            ->disableOriginalConstructor()
            ->getMock();

        $driver->method('getType')->will($this->returnValue('test-driver'));

        $database = new Database($driver, 'test', 'prefix_');

        $this->assertEquals('test', $database->getName());
        $this->assertEquals($driver, $database->driver());
        $this->assertEquals('prefix_', $database->getPrefix());
        $this->assertEquals('test-driver', $database->getType());
    }
}