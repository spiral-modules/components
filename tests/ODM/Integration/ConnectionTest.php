<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace ODM\Integration;

use Spiral\Tests\ODM\Integration\BaseTest;

class ConnectionTest extends BaseTest
{
    public function testConnected()
    {
        $result = $this->database->command(['ping' => 1]);
        $this->assertEquals(1, $result->toArray()[0]['ok']);
    }
}