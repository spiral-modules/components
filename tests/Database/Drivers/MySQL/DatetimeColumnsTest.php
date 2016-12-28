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
     * @expectedExceptionMessage SQLSTATE[HY000]: General error: 1293 Incorrect table definition;
     *                           there can be only one TIMESTAMP column with CURRENT_TIMESTAMP in
     *                           DEFAULT or ON UPDATE clause
     */
    public function testMultipleTimestampCurrentTimestamp()
    {
        parent::testMultipleTimestampCurrentTimestamp();
    }

    /**
     * @expectedException \Spiral\Database\Exceptions\HandlerException
     * @expectedExceptionMessage SQLSTATE[42000]: Syntax error or access violation: 1067 Invalid
     *                           default value for 'target'
     */
    public function testMultipleDatetimeCurrentTimestamp()
    {
        parent::testMultipleDatetimeCurrentTimestamp();
    }

    /**
     * @expectedException \Spiral\Database\Exceptions\HandlerException
     * @expectedExceptionMessage SQLSTATE[42000]: Syntax error or access violation: 1067 Invalid
     *                           default value for 'target'
     */
    public function testTimestampDatetimeZero()
    {
        parent::testTimestampDatetimeZero();
    }

    /**
     * @expectedException \Spiral\Database\Exceptions\HandlerException
     * @expectedExceptionMessage SQLSTATE[42000]: Syntax error or access violation: 1067 Invalid
     *                           default value for 'target'
     */
    public function testDatetimeCurrentTimestamp()
    {
        parent::testDatetimeCurrentTimestamp();
    }

    /**
     * @expectedException \Spiral\Database\Exceptions\HandlerException
     * @expectedExceptionMessage SQLSTATE[42000]: Syntax error or access violation: 1067 Invalid
     *                           default value for 'target'
     */
    public function testDatetimeCurrentTimestampNotNull()
    {
        parent::testDatetimeCurrentTimestampNotNull();
    }
}