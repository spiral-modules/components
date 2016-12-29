<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\Database\Drivers\SQLite;

class SelectQueryTest extends \Spiral\Tests\Database\Drivers\SelectQueryTest
{
    use DriverTrait;

    public function testOffsetNoLimit()
    {
        $select = $this->database->select()->from(['users'])->offset(20);

        $this->assertSameQuery(
            "SELECT * FROM {users} LIMIT -1 OFFSET 20",
            $select
        );
    }
}