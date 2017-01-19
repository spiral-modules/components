<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ORM;

use Spiral\ORM\Entities\Relations\ManyToManyRelation;
use Spiral\Tests\ORM\Fixtures\Post;
use Spiral\Tests\ORM\Fixtures\Tag;
use Spiral\Tests\ORM\Fixtures\User;

abstract class ManyToManyRelationTest extends BaseTest
{
    public function testInstance()
    {
        $post = new Post();
        $this->assertFalse($post->getRelations()->get('tags')->isLoaded());
        $this->assertTrue(empty($post->tags));

        $this->assertInstanceOf(ManyToManyRelation::class, $post->tags);
        $this->assertFalse($post->tags->isLeading());

        //Force loading
        $this->assertCount(0, $post->tags);
        $this->assertTrue($post->tags->isLoaded());

        //But still empty
        $this->assertTrue(empty($post->tags));
    }

    public function testAddLinkedNoSave()
    {
        $post = new Post();
        $post->tags->link(new Tag(['name' => 'tag a']));
        $post->tags->link(new Tag(['name' => 'tag b']));

        $this->assertFalse(empty($post->tags));
        $this->assertCount(2, $post->tags);
    }

    public function testMatchOneNull()
    {
        $post = new Post();
        $post->tags->link($tag1 = new Tag(['name' => 'tag a']));
        $post->tags->link($tag2 = new Tag(['name' => 'tag b']));

        $this->assertSame(null, $post->tags->matchOne(null));
        $this->assertFalse($post->tags->has(null));
    }

    public function testMatchOneEntity()
    {
        $post = new Post();
        $post->tags->link($tag1 = new Tag(['name' => 'tag a']));
        $post->tags->link($tag2 = new Tag(['name' => 'tag b']));

        $this->assertSame($tag1, $post->tags->matchOne($tag1));
        $this->assertTrue($post->tags->has($tag1));

    }

    public function testMatchOneQuery()
    {
        $post = new Post();
        $post->tags->link($tag1 = new Tag(['name' => 'tag a']));
        $post->tags->link($tag2 = new Tag(['name' => 'tag b']));

        $this->assertSame($tag1, $post->tags->matchOne(['name' => 'tag a']));
        $this->assertTrue($post->tags->has(['name' => 'tag a']));

        $this->assertSame(
            [$tag1],
            iterator_to_array($post->tags->matchMultiple(['name' => 'tag a']))
        );
    }

    public function testUnlinkInSession()
    {
        $post = new Post();
        $post->tags->link($tag1 = new Tag(['name' => 'tag a']));
        $post->tags->link($tag2 = new Tag(['name' => 'tag b']));

        $this->assertFalse(empty($post->tags));
        $this->assertCount(2, $post->tags);

        $post->tags->unlink($tag1);

        $this->assertFalse(empty($post->tags));
        $this->assertCount(1, $post->tags);

        $post->tags->unlink($tag2);

        $this->assertTrue(empty($post->tags));
        $this->assertCount(0, $post->tags);
    }

    public function testCreateWithLinking()
    {
        $post = new Post();
        $post->author = new User();
        $post->tags->link($tag1 = new Tag(['name' => 'tag a']));
        $post->tags->link($tag2 = new Tag(['name' => 'tag b']));

        $post->save();
        $this->assertSameInDB($post);
        $this->assertSameInDB($post->author);
        $this->assertSameInDB($tag1);
        $this->assertSameInDB($tag2);

        $this->assertCount(2, $this->db->post_tag_map);
    }
}