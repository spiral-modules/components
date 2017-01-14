<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ORM;

use Spiral\Tests\ORM\Fixtures\Post;
use Spiral\Tests\ORM\Fixtures\User;

abstract class StoreWithRelationsTest extends BaseTest
{
    /**
     * @expectedException \Spiral\Database\Exceptions\QueryException
     */
    public function testSaveWithBelongsToWithoutParent()
    {
        $post = new Post();
        $post->save();
    }

    public function testSaveWithParent()
    {
        $post = new Post();
        $post->author = new User();
        $post->save();

        $this->assertSameInDB($post);
        $this->assertSameInDB($post->author);
    }
}