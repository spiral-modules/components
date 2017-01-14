<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ORM;

use Spiral\ORM\Transaction;
use Spiral\Tests\ORM\Fixtures\User;

abstract class CreateTest extends BaseTest
{
    public function testNotLoaded()
    {
        /** @var User $user */
        $user = $this->orm->make(User::class);
        $this->assertFalse($user->isLoaded());
    }

    public function testSaveActiveRecordAndCheckLoaded()
    {
        /** @var User $user */
        $user = $this->orm->make(User::class);
        $this->assertFalse($user->isLoaded());

        $user->save();
        $this->assertTrue($user->isLoaded());
        $this->assertNotEmpty($user->primaryKey());
    }

    public function testStoreInTransactionAndCheckLoaded()
    {
        /** @var User $user */
        $user = $this->orm->make(User::class);
        $this->assertFalse($user->isLoaded());

        $transaction = new Transaction();
        $transaction->store($user);

        $this->assertTrue($user->isLoaded());
        $this->assertEmpty($user->primaryKey());
        $transaction->run();

        $this->assertTrue($user->isLoaded());
        $this->assertNotEmpty($user->primaryKey());
    }
}