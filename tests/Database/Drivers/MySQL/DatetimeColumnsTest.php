<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\Database\Drivers\MySQL;

class DatetimeColumnsTest extends \Spiral\Tests\Database\Drivers\DatetimeColumnsTest
{
    use DriverTrait;

    /**
     * @expectedException \Spiral\Database\Exceptions\HandlerException
     */
    public function testMultipleTimestampCurrentTimestamp()
    {
        parent::testMultipleTimestampCurrentTimestamp();
    }

    /**
     * @expectedException \Spiral\Database\Exceptions\HandlerException
     */
    public function testMultipleDatetimeCurrentTimestamp()
    {
        parent::testMultipleDatetimeCurrentTimestamp();
    }
}