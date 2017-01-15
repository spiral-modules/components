<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ORM;

use Spiral\ORM\Entities\Loaders\RelationLoader;
use Spiral\ORM\Entities\Relations\HasManyRelation;
use Spiral\Tests\ORM\Fixtures\Comment;
use Spiral\Tests\ORM\Fixtures\Post;
use Spiral\Tests\ORM\Fixtures\User;

abstract class HasManyRelationTest extends BaseTest
{
//    public function testInstance()
//    {
//        $post = new Post();
//        $this->assertFalse($post->getRelations()->get('comments')->isLoaded());
//        $this->assertInstanceOf(HasManyRelation::class, $post->comments);
//        $this->assertFalse($post->comments->isLeading());
//        $this->assertCount(0, $post->comments);
//        $this->assertTrue($post->comments->isLoaded());
//    }
//
//    public function testAddInstanceAndSave()
//    {
//        $post = new Post();
//        $post->author = new User();
//        $post->comments->add($comment = new Comment(['message' => 'hi']));
//        $this->assertCount(1, $post->comments);
//
//        $post->save();
//
//        $this->assertSameInDB($post);
//        $this->assertSameInDB($comment);
//    }
//
//    public function testAddMultipleInstancesAndSave()
//    {
//        $post = new Post();
//        $post->author = new User();
//        $post->comments->add($comment = new Comment(['message' => 'hi']));
//        $post->comments->add($comment2 = new Comment(['message' => 'hi2']));
//
//        $this->assertCount(2, $post->comments);
//
//        $post->save();
//
//        $this->assertSameInDB($post);
//        $this->assertSameInDB($comment);
//        $this->assertSameInDB($comment2);
//    }
//
//    /**
//     * @expectedException \Spiral\ORM\Exceptions\RelationException
//     * @expectedExceptionMessage Must be an instance of 'Spiral\Tests\ORM\Fixtures\Comment',
//     *                           'Spiral\Tests\ORM\Fixtures\User' given
//     */
//    public function testSetWrongInstance()
//    {
//        $post = new Post();
//        $post->comments->add(new User());
//    }

    public function testSaveAndHasAndPostload()
    {
        $post = new Post();
        $post->author = new User();
        $post->comments->add($comment = new Comment(['message' => 'hi']));
        $post->comments->add($comment2 = new Comment(['message' => 'hi2']));
        $post->comments->add($comment3 = new Comment(['message' => 'hi3']));

        $this->assertTrue($post->comments->has($comment));
        $this->assertTrue($post->comments->has($comment2->getFields()));
        $this->assertTrue($post->comments->has(['message' => 'hi3']));
        $this->assertCount(3, $post->comments);

        $post->save();

//        $this->assertSameInDB($post);
//        $this->assertSameInDB($comment);
//        $this->assertSameInDB($comment2);
//        $this->assertSameInDB($comment3);
//
//        /** @var Post $dbPost */
//        $dbPost = $this->orm->selector(Post::class)
//            ->wherePK($post->primaryKey())
//            ->load('comments', ['method' => RelationLoader::POSTLOAD])
//            ->findOne();
//
//        $this->assertTrue($dbPost->getRelations()->get('comments')->isLoaded());
//
//        $this->assertCount(3, $dbPost->comments);
//
//        $this->assertTrue($dbPost->comments->has($comment));
//        $this->assertTrue($dbPost->comments->has($comment2->getFields()));
//        $this->assertTrue($dbPost->comments->has(['message' => 'hi3']));
    }

    //find one
    //find multiple
    //delete
    //delete in session
}