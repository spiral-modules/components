<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ORM;

use Spiral\ORM\Entities\Loaders\RelationLoader;
use Spiral\Tests\ORM\Fixtures\Comment;
use Spiral\Tests\ORM\Fixtures\Post;
use Spiral\Tests\ORM\Fixtures\User;

abstract class NestedRelationsTest extends BaseTest
{
    public function testParentWithChild()
    {
        $post = new Post();
        $post->author = new User();
        $post->author->profile->bio = 'hello world';
        $post->comments->add($comment = new Comment(['message' => 'hi']));

        $post->save();

        /**
         * @var Post $dbPost
         */
        $dbPost = $this->orm->selector(Post::class)
            ->load('author.profile')
            ->load('comments')
            ->findOne();

        $this->assertTrue($dbPost->getRelations()->get('author')->isLoaded());
        $this->assertTrue($dbPost->author->getRelations()->get('profile')->isLoaded());
        $this->assertTrue($dbPost->getRelations()->get('comments')->isLoaded());

        $this->assertSame($post->primaryKey(), $dbPost->primaryKey());

        $this->assertSimilar($post->author, $dbPost->author);
        $this->assertSimilar($post->author->profile, $dbPost->author->profile);
        $this->assertCount(1, $dbPost->comments);
    }

    public function testAlternativeLoadWithChild()
    {
        $post = new Post();
        $post->author = new User();
        $post->author->profile->bio = 'hello world';
        $post->comments->add($comment = new Comment(['message' => 'hi']));

        $post->save();

        /**
         * @var Post $dbPost
         */
        $dbPost = $this->orm->selector(Post::class)
            ->load(['author.profile', 'comments'])
            ->findOne();

        $this->assertTrue($dbPost->getRelations()->get('author')->isLoaded());
        $this->assertTrue($dbPost->author->getRelations()->get('profile')->isLoaded());
        $this->assertTrue($dbPost->getRelations()->get('comments')->isLoaded());

        $this->assertSame($post->primaryKey(), $dbPost->primaryKey());

        $this->assertSimilar($post->author, $dbPost->author);
        $this->assertSimilar($post->author->profile, $dbPost->author->profile);
        $this->assertCount(1, $dbPost->comments);
    }

    public function testAlternative2LoadWithChild()
    {
        $post = new Post();
        $post->author = new User();
        $post->author->profile->bio = 'hello world';
        $post->comments->add($comment = new Comment(['message' => 'hi']));

        $post->save();

        /**
         * @var Post $dbPost
         */
        $dbPost = $this->orm->selector(Post::class)
            ->load([
                'author.profile' => ['method' => RelationLoader::INLOAD],
                'comments'       => ['method' => RelationLoader::INLOAD]
            ])
            ->findOne();

        $this->assertTrue($dbPost->getRelations()->get('author')->isLoaded());
        $this->assertTrue($dbPost->author->getRelations()->get('profile')->isLoaded());
        $this->assertTrue($dbPost->getRelations()->get('comments')->isLoaded());

        $this->assertSame($post->primaryKey(), $dbPost->primaryKey());

        $this->assertSimilar($post->author, $dbPost->author);
        $this->assertSimilar($post->author->profile, $dbPost->author->profile);
        $this->assertCount(1, $dbPost->comments);
    }

    public function testAlternativeWithWithChild()
    {
        $post = new Post();
        $post->author = new User();
        $post->author->profile->bio = 'hello world';
        $post->comments->add($comment = new Comment(['message' => 'hi']));

        $post->save();

        /**
         * @var Post $dbPost
         */
        $dbPost = $this->orm->selector(Post::class)
            ->with(['author.profile', 'comments'])
            ->findOne();

        $this->assertFalse($dbPost->getRelations()->get('author')->isLoaded());
        $this->assertFalse($dbPost->author->getRelations()->get('profile')->isLoaded());
        $this->assertFalse($dbPost->getRelations()->get('comments')->isLoaded());

        $this->assertSame($post->primaryKey(), $dbPost->primaryKey());

        $this->assertSimilar($post->author, $dbPost->author);
        $this->assertSimilar($post->author->profile, $dbPost->author->profile);
        $this->assertCount(1, $dbPost->comments);
    }


    public function testAlternative2WithWithChild()
    {
        $post = new Post();
        $post->author = new User();
        $post->author->profile->bio = 'hello world';
        $post->comments->add($comment = new Comment(['message' => 'hi']));

        $post->save();

        /**
         * @var Post $dbPost
         */
        $dbPost = $this->orm->selector(Post::class)
            ->with([
                'author.profile' => ['alias' => 'user_profile'],
                'comments'       => ['alias' => 'some_comments']
            ])
            ->findOne();

        $this->assertFalse($dbPost->getRelations()->get('author')->isLoaded());
        $this->assertFalse($dbPost->author->getRelations()->get('profile')->isLoaded());
        $this->assertFalse($dbPost->getRelations()->get('comments')->isLoaded());

        $this->assertSame($post->primaryKey(), $dbPost->primaryKey());

        $this->assertSimilar($post->author, $dbPost->author);
        $this->assertSimilar($post->author->profile, $dbPost->author->profile);
        $this->assertCount(1, $dbPost->comments);
    }

    public function testParentWithChildNoProfile()
    {
        $post = new Post();
        $post->author = new User();
        $post->comments->add($comment = new Comment(['message' => 'hi']));

        $post->save();

        /**
         * @var Post $dbPost
         */
        $dbPost = $this->orm->selector(Post::class)
            ->load('author.profile')
            ->load('comments')
            ->findOne();

        $this->assertTrue($dbPost->getRelations()->get('author')->isLoaded());
        $this->assertTrue($dbPost->author->getRelations()->get('profile')->isLoaded());
        $this->assertTrue($dbPost->getRelations()->get('comments')->isLoaded());

        $this->assertSame($post->primaryKey(), $dbPost->primaryKey());

        $this->assertSimilar($post->author, $dbPost->author);
        $this->assertFalse($post->author->profile->isLoaded());
        $this->assertCount(1, $dbPost->comments);
    }

    public function testParentWithChildNoComments()
    {
        $post = new Post();
        $post->author = new User();
        $post->author->profile->bio = 'hello world';

        $post->save();

        /**
         * @var Post $dbPost
         */
        $dbPost = $this->orm->selector(Post::class)
            ->load('author.profile')
            ->load('comments')
            ->findOne();

        $this->assertTrue($dbPost->getRelations()->get('author')->isLoaded());
        $this->assertTrue($dbPost->author->getRelations()->get('profile')->isLoaded());
        $this->assertTrue($dbPost->getRelations()->get('comments')->isLoaded());

        $this->assertSame($post->primaryKey(), $dbPost->primaryKey());

        $this->assertSimilar($post->author, $dbPost->author);
        $this->assertSimilar($post->author->profile, $dbPost->author->profile);
        $this->assertCount(0, $dbPost->comments);
    }
}