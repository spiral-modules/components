<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\Database\Drivers\MySQL;

class SelectQueryTest extends \Spiral\Tests\Database\Drivers\SelectQueryTest
{
    use DriverTrait;

    public function testOffsetNoLimit()
    {
        $select = $this->database->select()->from(['users'])->offset(20);

        $this->assertSameQuery(
            "SELECT * FROM {users} LIMIT 18446744073709551615 OFFSET 20",
            $select
        );
    }
}