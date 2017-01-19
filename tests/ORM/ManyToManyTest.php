<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ORM;

use Spiral\ORM\Entities\Relations\ManyToManyRelation;
use Spiral\Tests\ORM\Fixtures\Post;

abstract class ManyToManyTest extends BaseTest
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
}