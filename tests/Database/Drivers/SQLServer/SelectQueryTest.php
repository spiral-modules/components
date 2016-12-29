<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\Database\Drivers\SQLServer;

class SelectQueryTest extends \Spiral\Tests\Database\Drivers\SelectQueryTest
{
    use DriverTrait;

    public function testLimitNoOffset()
    {
        $select = $this->database->select()->from(['users'])->limit(10);

        $this->assertSameQuery(
            "SELECT * FROM {users} OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY",
            $select
        );
    }

    public function testLimitAndOffset()
    {
        $select = $this->database->select()->from(['users'])->limit(10)->offset(20);

        $this->assertSame(10, $select->getLimit());
        $this->assertSame(20, $select->getOffset());

        $this->assertSameQuery(
            "SELECT * FROM {users} OFFSET 20 ROWS FETCH NEXT 10 ROWS ONLY",
            $select
        );
    }

    public function testOffsetNoLimit()
    {
        $select = $this->database->select()->from(['users'])->offset(20);

        $this->assertSameQuery(
            "SELECT * FROM {users} OFFSET 20 ROWS",
            $select
        );
    }
}