<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */
namespace ODM\Integration;

use Spiral\Tests\ODM\Fixtures\User;
use Spiral\Tests\ODM\Integration\BaseTest;

class ConnectionTest extends BaseTest
{
    public function testConnected()
    {
        $result = $this->database->command(['ping' => 1]);
        $this->assertEquals(1, $result->toArray()[0]['ok']);
    }

    /**
     * @expectedException \Spiral\ODM\Exceptions\ODMException
     */
    public function testBadDefine()
    {
        $this->odm->define('abc', 123);
    }

    /**
     * @expectedException \Spiral\ODM\Exceptions\ODMException
     */
    public function testBadDefineValidClass()
    {
        $this->odm->define(User::class, 123);
    }
}