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

    public function testStoreAndUpdate()
    {
        $user = new User();
        $user->name = 'Anton';
        $user->save();

        $this->assertSameInDB($user);

        $user->name = 'John';
        $user->save();

        $this->assertSameInDB($user);
    }

    public function testStoreAndUpdateDirty()
    {
        $user = new User();
        $user->name = 'Anton';
        $user->save();

        $this->assertSameInDB($user);

        $user->solidState(false);
        $user->name = 'John';
        $user->save();

        $this->assertSameInDB($user);
    }

    public function testStoreAndDelete()
    {
        $user = new User();
        $user->name = 'Anton';
        $user->save();

        $this->assertSameInDB($user);
        $this->assertSame(1, $this->dbal->database()->users->count());

        $user->delete();
        $this->assertFalse($user->isLoaded());

        $this->assertSame(0, $this->dbal->database()->users->count());
    }
}