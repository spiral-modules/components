<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\Tests\ORM;

use Spiral\Tests\ORM\Fixtures\Comment;
use Spiral\Tests\ORM\Fixtures\Post;
use Spiral\Tests\ORM\Fixtures\Profile;
use Spiral\Tests\ORM\Fixtures\Supertag;
use Spiral\Tests\ORM\Fixtures\Tag;
use Spiral\Tests\ORM\Fixtures\User;

abstract class ManyToMorphedRelationTest extends BaseTest
{
    const MODELS = [
        User::class,
        Post::class,
        Comment::class,
        Tag::class,
        Profile::class,
        Supertag::class
    ];

    public function testSchemaBuilding()
    {
        $schema = $this->db->table('tagged_map')->getSchema();
        $this->assertTrue($schema->hasColumn('supertag_id'));
        $this->assertTrue($schema->hasColumn('tagged_id'));
        $this->assertTrue($schema->hasColumn('tagged_type'));
    }

    /*
     * Testing inversed relations.
     */
    public function testLinkInversed()
    {
        $user = new User();
        $user->supertags->link(new Supertag(['name' => 'A']));
        $user->save();
    }

    //todo: other tests
}