<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ORM;

use Spiral\ORM\Entities\Loaders\RelationLoader;
use Spiral\Tests\ORM\Fixtures\User;

abstract class HasOneRelationTest extends BaseTest
{
    public function testCreateWithNewRelation()
    {
        $user = new User();
        $user->name = 'Some name';
        $user->profile->bio = 'Some bio';
        $user->save();

        $this->assertTrue($user->getRelations()->get('profile')->isLoaded());

        $this->assertSameInDB($user);
        $this->assertSameInDB($user->profile);
    }

    public function testUpdateRelation()
    {
        $user = new User();
        $user->name = 'Some name';
        $user->profile->bio = 'Some bio';
        $user->save();

        $this->assertTrue($user->getRelations()->get('profile')->isLoaded());

        $this->assertSameInDB($user);
        $this->assertSameInDB($user->profile);

        $user->profile->bio = 'new bio';
        $user->save();

        $this->assertSameInDB($user->profile);
    }

    public function testSaveAndPostLoad()
    {
        $user = new User();
        $user->name = 'Some name';
        $user->profile->bio = 'Some bio';
        $user->save();

        $dbUser = $this->orm->selector(User::class)
            ->load('profile', ['method' => RelationLoader::POSTLOAD])
            ->findOne();

        $this->assertFalse($dbUser->getRelations()->get('profile')->isLeading());
        $this->assertTrue($dbUser->getRelations()->get('profile')->isLoaded());

        $this->assertEquals($user->getFields(), $dbUser->getFields());
        $this->assertEquals($user->profile->getFields(), $dbUser->profile->getFields());
    }

    public function testSaveAndInload()
    {
        $user = new User();
        $user->name = 'Some name';
        $user->profile->bio = 'Some bio';
        $user->save();

        $dbUser = $this->orm->selector(User::class)
            ->load('profile', ['method' => RelationLoader::INLOAD])
            ->findOne();

        $this->assertFalse($dbUser->getRelations()->get('profile')->isLeading());
        $this->assertTrue($dbUser->getRelations()->get('profile')->isLoaded());

        $this->assertEquals($user->getFields(), $dbUser->getFields());
        $this->assertEquals($user->profile->getFields(), $dbUser->profile->getFields());
    }
}