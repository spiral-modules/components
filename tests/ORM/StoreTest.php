<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ORM;

use Spiral\ORM\ORMInterface;
use Spiral\ORM\Schemas\SchemaBuilder;
use Spiral\ORM\Transaction;
use Spiral\Tests\ORM\Fixtures\User;

abstract class StoreTest extends BaseTest
{
    public function testSchema()
    {
        $this->assertInstanceOf(SchemaBuilder::class, $this->orm->schemaBuilder(false));
    }

    public function testNotLoaded()
    {
        /** @var User $user */
        $user = $this->orm->make(User::class);
        $this->assertInstanceOf(User::class, $user);

        $this->assertFalse($user->isLoaded());
    }

    /**
     * @expectedException \Spiral\ORM\Exceptions\InstantionException
     */
    public function testFiledMake()
    {
        /** @var User $user */
        $user = $this->orm->make(User::class, [], ORMInterface::STATE_DELETED);
    }

    public function testSaveActiveRecordAndCheckLoaded()
    {
        /** @var User $user */
        $user = $this->orm->make(User::class);
        $this->assertInstanceOf(User::class, $user);

        $this->assertFalse($user->isLoaded());

        $user->save();
        $this->assertTrue($user->isLoaded());
        $this->assertNotEmpty($user->primaryKey());
    }

    public function testSaveIntoTransaction()
    {
        /** @var User $user */
        $user = $this->orm->make(User::class);
        $this->assertInstanceOf(User::class, $user);

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
        /** @var User $user */
        $user = $this->orm->make(User::class);
        $this->assertInstanceOf(User::class, $user);

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