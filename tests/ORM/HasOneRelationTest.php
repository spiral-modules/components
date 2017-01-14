<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ORM;

use Spiral\ORM\Entities\Loaders\RelationLoader;
use Spiral\Tests\ORM\Fixtures\Profile;
use Spiral\Tests\ORM\Fixtures\User;

abstract class HasOneRelationTest extends BaseTest
{
    public function testCreateWithNewRelation()
    {
        $user = new User();
        $user->name = 'Some name';
        $user->profile->bio = 'Some bio';
        $user->save();

        $this->assertInstanceOf(Profile::class, $user->profile);

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

    public function testSaveAndLazyLoad()
    {
        $user = new User();
        $user->name = 'Some name';
        $user->profile->bio = 'Some bio';
        $user->save();

        $dbUser = $this->orm->selector(User::class)->findOne();

        $this->assertFalse($dbUser->getRelations()->get('profile')->isLoaded());

        $this->assertEquals($user->getFields(), $dbUser->getFields());
        $this->assertEquals($user->profile->getFields(), $dbUser->profile->getFields());
    }

    public function testDeleteAssociated()
    {
        $user = new User();
        $user->name = 'Some name';
        $user->profile->bio = 'Some bio';
        $user->save();
        $this->assertSame(1, $this->db->profiles->count());

        $user->profile->delete();
        $this->assertSame(0, $this->db->profiles->count());
        $this->assertFalse($user->profile->isLoaded());
    }

    public function testSetAssociatedNull()
    {
        $user = new User();
        $user->name = 'Some name';
        $user->profile->bio = 'Some bio';
        $user->save();

        $this->assertSame(1, $this->db->profiles->count());

        //Back up
        $profile = $user->profile;

        $user->profile = null;

        //Null command expected
        $user->solidState(false);
        $user->save();

        //New instance
        $this->assertInstanceOf(Profile::class, $user->profile);
        $this->assertNotSame($profile, $user->profile);

        $this->assertSame(0, $this->db->profiles->count());
        $this->assertFalse($user->profile->isLoaded());
    }

    public function testSetAssociatedMultipleNull()
    {
        $user = new User();
        $user->name = 'Some name';
        $user->profile->bio = 'Some bio';
        $user->save();

        $this->assertSame(1, $this->db->profiles->count());

        //Back up
        $profile = $user->profile;

        $user->profile = null;
        $user->profile->bio = 'sample bio';
        $user->profile = null;

        //Null command expected
        $user->solidState(false);
        $user->save();

        //New instance
        $this->assertInstanceOf(Profile::class, $user->profile);
        $this->assertNotSame($profile, $user->profile);

        $this->assertSame(0, $this->db->profiles->count());
        $this->assertFalse($user->profile->isLoaded());
    }
}