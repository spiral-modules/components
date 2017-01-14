<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ORM;

use Spiral\ORM\Transaction;
use Spiral\Tests\ORM\Fixtures\User;

abstract class StoreInScopeTest extends BaseTest
{
    public function testNotLoaded()
    {
        $user = new User();

        $this->assertFalse($user->isLoaded());
    }

    public function testSaveActiveRecordAndCheckLoaded()
    {
        $user = new User();

        $this->assertFalse($user->isLoaded());

        $user->save();
        $this->assertTrue($user->isLoaded());
        $this->assertNotEmpty($user->primaryKey());
    }

    public function testSaveIntoTransaction()
    {
        $user = new User();
        $this->assertFalse($user->isLoaded());

        $transaction = new Transaction();
        $user->save($transaction);

        $this->assertTrue($user->isLoaded());
        $this->assertEmpty($user->primaryKey());

        $transaction->run();

        $this->assertTrue($user->isLoaded());
        $this->assertNotEmpty($user->primaryKey());
    }

    public function testStoreInTransactionAndCheckLoaded()
    {
        $user = new User();

        $this->assertFalse($user->isLoaded());

        $transaction = new Transaction();
        $transaction->store($user);

        $this->assertTrue($user->isLoaded());
        $this->assertEmpty($user->primaryKey());

        $transaction->run();

        $this->assertTrue($user->isLoaded());
        $this->assertNotEmpty($user->primaryKey());

        $this->assertSameInDB($user);
    }
}