<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */
namespace Spiral\Tests\Database\MySQL;

/**
 * MySQL 5.6 and lower
 */
class DatetimeColumnsTest extends \Spiral\Tests\Database\DatetimeColumnsTest
{
    use DriverTrait;

    public function setUp()
    {
        parent::setUp();
        $pdo = $this->database->getDriver()->getPDO();
        $version = $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);

        if (version_compare('5.6', $version, '<')) {
            $this->markTestSkipped('TestCase is specific to < 5.6 drivers only');
        }
    }

    /**
     * @expectedException \Spiral\Database\Exceptions\SchemaHandlerException
     * @expectedExceptionMessage SQLSTATE[HY000]: General error: 1293 Incorrect table definition;
     *                           there can be only one TIMESTAMP column with CURRENT_TIMESTAMP in
     *                           DEFAULT or ON UPDATE clause
     */
    public function testMultipleTimestampCurrentTimestamp()
    {
        parent::testMultipleTimestampCurrentTimestamp();
    }

    /**
     * @expectedException \Spiral\Database\Exceptions\SchemaHandlerException
     * @expectedExceptionMessage SQLSTATE[42000]: Syntax error or access violation: 1067 Invalid
     *                           default value for 'target'
     */
    public function testMultipleDatetimeCurrentTimestamp()
    {
        parent::testMultipleDatetimeCurrentTimestamp();
    }

    /**
     * @expectedException \Spiral\Database\Exceptions\SchemaHandlerException
     * @expectedExceptionMessage SQLSTATE[42000]: Syntax error or access violation: 1067 Invalid
     *                           default value for 'target'
     */
    public function testTimestampDatetimeZero()
    {
        parent::testTimestampDatetimeZero();
    }

    /**
     * @expectedException \Spiral\Database\Exceptions\SchemaHandlerException
     * @expectedExceptionMessage SQLSTATE[42000]: Syntax error or access violation: 1067 Invalid
     *                           default value for 'target'
     */
    public function testDatetimeCurrentTimestamp()
    {
        parent::testDatetimeCurrentTimestamp();
    }

    /**
     * @expectedException \Spiral\Database\Exceptions\SchemaHandlerException
     * @expectedExceptionMessage SQLSTATE[42000]: Syntax error or access violation: 1067 Invalid
     *                           default value for 'target'
     */
    public function testDatetimeCurrentTimestampNotNull()
    {
        parent::testDatetimeCurrentTimestampNotNull();
    }
}