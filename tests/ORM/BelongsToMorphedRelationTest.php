<?php
/**
 * components
 *
 * @author    Wolfy-J
 */

namespace Spiral\Tests\ORM;

use Spiral\Tests\ORM\Fixtures\Comment;
use Spiral\Tests\ORM\Fixtures\Picture;
use Spiral\Tests\ORM\Fixtures\Post;
use Spiral\Tests\ORM\Fixtures\Profile;
use Spiral\Tests\ORM\Fixtures\Tag;
use Spiral\Tests\ORM\Fixtures\User;

abstract class BelongsToMorphedRelationTest extends BaseTest
{
    const MODELS = [
        User::class,
        Post::class,
        Comment::class,
        Tag::class,
        Profile::class,
        Picture::class
    ];

//    public function testSchemaBuilding()
//    {
//        $picture = new Picture();
//        $this->assertTrue($picture->hasField('parent_id'));
//        $this->assertTrue($picture->hasField('parent_type'));
//    }
//
//    public function testSetParent()
//    {
//        $picture = new Picture();
//        $picture->parent = $user = new User();
//        $picture->save();
//
//        $this->assertSameInDB($picture);
//
//        $this->assertEquals('user', $picture->parent_type);
//        $this->assertEquals($user->primaryKey(), $picture->parent_id);
//    }
//
//    public function testChangeParent()
//    {
//        $picture = new Picture();
//        $picture->parent = $user = new User();
//        $picture->save();
//
//        $this->assertSameInDB($picture);
//
//        $this->assertEquals('user', $picture->parent_type);
//        $this->assertEquals($user->primaryKey(), $picture->parent_id);
//
//        $picture->parent = $post = new Post();
//        $picture->parent->author = $user;
//        $picture->save();
//
//        $this->assertSameInDB($picture);
//
//        $this->assertEquals('post', $picture->parent_type);
//        $this->assertEquals($post->primaryKey(), $picture->parent_id);
//    }
//
//    public function testLazyLoad()
//    {
//        $picture = new Picture();
//        $picture->parent = $user = new User();
//        $picture->save();
//
//        $picture = $this->orm->source(Picture::class)->findByPK($picture->primaryKey());
//        $this->assertSimilar($user, $picture->parent);
//
//        $picture->parent = $post = new Post();
//        $picture->parent->author = $user;
//        $picture->save();
//
//        $picture = $this->orm->source(Picture::class)->findByPK($picture->primaryKey());
//        $this->assertSimilar($post, $picture->parent);
//    }
//
//    public function testSetNull()
//    {
//        $picture = new Picture();
//        $picture->parent = $user = new User();
//        $picture->save();
//
//        $picture = $this->orm->source(Picture::class)->findByPK($picture->primaryKey());
//        $this->assertSimilar($user, $picture->parent);
//
//        $picture->parent = null;
//        $picture->save();
//
//        $picture = $this->orm->source(Picture::class)->findByPK($picture->primaryKey());
//        $this->assertSame(null, $picture->parent);
//    }

    public function testInversedLazy()
    {
        $picture = new Picture();
        $picture->parent = $user = new User();
        $picture->save();

        $user = $this->orm->source(User::class)->findByPK($user->primaryKey());

        $this->assertSimilar($picture, $user->picture);

        $picture->parent = $post = new Post();
        $picture->parent->author = $user;
        $picture->save();

        $post = $this->orm->source(Post::class)->findByPK($user->primaryKey());
        $this->assertSimilar($picture, $post->picture);
    }
}