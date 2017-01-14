<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ORM;

use Spiral\ORM\Entities\Loaders\RelationLoader;
use Spiral\ORM\Entities\Relations\BelongsToRelation;
use Spiral\ORM\Transaction;
use Spiral\Tests\ORM\Fixtures\Comment;
use Spiral\Tests\ORM\Fixtures\Post;
use Spiral\Tests\ORM\Fixtures\Recursive;
use Spiral\Tests\ORM\Fixtures\User;

abstract class BelongsToRelationTest extends BaseTest
{
    public function testCreateWithNewRelation()
    {
        $post = new Post();
        $post->title = 'New post';
        $this->assertFalse($post->getRelations()->get('author')->isLoaded());

        //NULL!
        $this->assertNull($post->author);

        $this->assertTrue($post->getRelations()->get('author')->isLoaded());

        $post->author = $user = new User();
        $user->name = 'Bobby';

        $this->assertInstanceOf(User::class, $post->author);
        $this->assertTrue($post->getRelations()->get('author')->isLoaded());

        $this->assertInstanceOf(BelongsToRelation::class, $post->getRelations()->get('author'));

        $this->assertSame($user, $post->author);

        $post->save();

        $this->assertSameInDB($post);
        $this->assertSameInDB($user);
    }

    /**
     * @expectedException \Spiral\ORM\Exceptions\RelationException
     * @expectedExceptionMessage Must be an instance of 'Spiral\Tests\ORM\Fixtures\User',
     *                           'Spiral\Tests\ORM\Fixtures\Comment' given
     */
    public function testSetWrongInstance()
    {
        $post = new Post();
        $post->author = new Comment();
    }

    /**
     * @expectedException \Spiral\Database\Exceptions\QueryException
     */
    public function testSaveButNull()
    {
        $post = new Post();
        $post->save();
    }

    /**
     * @expectedException \Spiral\ORM\Exceptions\RelationException
     * @expectedExceptionMessage Relation is not nullable
     */
    public function testForceSaveNull()
    {
        $post = new Post();
        $post->author = null;
        $post->save();
    }

    /**
     * @expectedException \Spiral\ORM\Exceptions\RelationException
     * @expectedExceptionMessage No data presented in non nullable relation
     */
    public function testForceSaveNullYouCantChangeIt()
    {
        $post = new Post();
        $post->getRelations()->get('author');

        $post->save();
    }

    public function testMultipleWithSameParent()
    {
        $post1 = new Post();
        $post2 = new Post();

        $post1->author = new User();
        $post1->author->name = 'Anton';

        $post2->author = $post1->author;

        $this->assertSame($post1->author, $post2->author);

        $transaction = new Transaction();
        $transaction->store($post1);
        $transaction->store($post2);
        $transaction->run();

        $this->assertSameInDB($post1);
        $this->assertSameInDB($post2);
        $this->assertSameInDB($post1->author);
    }

    public function testChangeParents()
    {
        $post = new Post();

        $user = new User();
        $user2 = new User();

        $post->author = $user;
        $post->save();

        $post->author = $user2;
        $post->save();

        $this->assertSameInDB($post);
    }

    public function testChangeParentsInsideTransaction()
    {
        $post = new Post();

        $user = new User();
        $user2 = new User();

        $transaction = new Transaction();

        $post->author = $user;
        $transaction->store($post);
        $post->author = $user2;
        $transaction->store($post);

        $transaction->run();
        $this->assertSameInDB($post);
    }

    public function testChangeParentsFromSavedToNotSavedInTransaction()
    {
        $post = new Post();

        $user = new User();
        $user->save();
        $user2 = new User();

        $transaction = new Transaction();

        $post->author = $user;
        $transaction->store($post);
        $post->author = $user2;
        $transaction->store($post);

        $transaction->run();
        $this->assertSameInDB($post);
    }

    public function testChangeParentsFromSavedToNotSaved()
    {
        $post = new Post();

        $user = new User();
        $user->save();
        $user2 = new User();

        $transaction = new Transaction();

        $post->author = $user;
        $post->save();
        $post->author = $user2;
        $post->save();

        $transaction->run();
        $this->assertSameInDB($post);
    }

    public function testChangeParentsFromNotSavedSaved()
    {
        $post = new Post();
        $user = new User();

        $user2 = new User();
        $user2->save();

        $transaction = new Transaction();

        $post->author = $user;
        $post->save();
        $post->author = $user2;
        $post->save();

        $transaction->run();
        $this->assertSameInDB($post);
    }

    public function testUpdateParent()
    {
        $post = new Post();
        $post->title = 'New post';
        $post->author = new User();
        $post->author->name = 'Bobby';
        $post->save();

        $this->assertSameInDB($post);

        $post->author->name = 'Antony';
        $post->save();

        $this->assertSameInDB($post);
    }

    public function testUpdateParentMultipleTimes()
    {
        $post = new Post();
        $post->title = 'New post';
        $post->author = new User();
        $post->author->name = 'Bobby';
        $post->save();

        $this->assertSameInDB($post);

        $post->author->name = 'Antony';
        $post->save();

        $this->assertSameInDB($post);

        $post->author->name = 'Victor';
        $post->save();

        $this->assertSameInDB($post);
    }

    public function testSaveAndPostLoad()
    {
        $post = new Post();
        $post->title = 'New post';
        $post->author = new User();
        $post->author->name = 'Bobby';
        $post->save();

        $dbPost = $this->orm->selector(Post::class)
            ->load('author', ['method' => RelationLoader::POSTLOAD])
            ->findOne();

        $this->assertTrue($dbPost->getRelations()->get('author')->isLeading());
        $this->assertTrue($dbPost->getRelations()->get('author')->isLoaded());

        $this->assertEquals($post->getFields(), $dbPost->getFields());
        $this->assertEquals($post->author->getFields(), $dbPost->author->getFields());
    }

    public function testSaveAndPostInload()
    {
        $post = new Post();
        $post->title = 'New post';
        $post->author = new User();
        $post->author->name = 'Bobby';
        $post->save();

        $dbPost = $this->orm->selector(Post::class)
            ->load('author', ['method' => RelationLoader::INLOAD])
            ->findOne();

        $this->assertTrue($dbPost->getRelations()->get('author')->isLeading());
        $this->assertTrue($dbPost->getRelations()->get('author')->isLoaded());

        $this->assertEquals($post->getFields(), $dbPost->getFields());
        $this->assertEquals($post->author->getFields(), $dbPost->author->getFields());
    }

    public function testSaveAndLazyLoad()
    {
        $post = new Post();
        $post->title = 'New post';
        $post->author = new User(['name' => 'Bobby']);
        $post->save();

        $dbPost = $this->orm->selector(Post::class)->findOne();

        $this->assertFalse($dbPost->getRelations()->get('author')->isLoaded());

        $this->assertEquals($post->getFields(), $dbPost->getFields());
        $this->assertEquals($post->author->getFields(), $dbPost->author->getFields());
    }

    public function testRecursive()
    {
        $recursive = new Recursive();
        $recursive->parent = $recursive1 = new Recursive();
        $recursive1->parent = $recursive2 = new Recursive();
        $recursive2->parent = $recursive3 = new Recursive();

        $recursive->save();

        $this->assertSameInDB($recursive);
        $this->assertSameInDB($recursive1);
        $this->assertSameInDB($recursive2);
        $this->assertSameInDB($recursive3);
    }
}