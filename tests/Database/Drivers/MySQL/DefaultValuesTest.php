<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\Database\Drivers\MySQL;

class DefaultValuesTest extends \Spiral\Tests\Database\Drivers\DefaultValuesTest
{
    use DriverTrait;

    /**
     * @expectedException \Spiral\Database\Exceptions\Drivers\MySQLDriverException
     * @expectedExceptionMessage Column table.target of type text/blob can not have non empty
     *                           default value
     */
    public function testTextDefaultValueString()
    {
        parent::testTextDefaultValueString();
    }
}