<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ORM;

use Spiral\Tests\ORM\Fixtures\User;

abstract class CreateTest extends BaseTest
{
    public function testCreate()
    {
        /** @var User $user */
        $user = $this->orm->make(User::class);
        $this->assertFalse($user->isLoaded());
    }
}