<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ODM\Integration;

use MongoDB\Driver\Cursor;
use Spiral\ODM\Entities\DocumentCursor;
use Spiral\Tests\ODM\Fixtures\Admin;
use Spiral\Tests\ODM\Fixtures\DataPiece;
use Spiral\Tests\ODM\Fixtures\User;

/**
 * Test data storage and operations with real mongo (there is no other way to test Cursor since
 * it's final).
 *
 * Only when MongoDatabase configured.
 */
class SelectionTest extends BaseTest
{
    public function testForeachWithInheritance()
    {
        $this->assertSame(0, $this->odm->source(User::class)->count());

        for ($i = 0; $i < 10; $i++) {
            $user = $this->odm->source(User::class)->create();
            $this->assertInstanceOf(User::class, $user);

            $user->name = 'Anton';
            $user->piece->value = $i;
            $user->save();
        }

        for ($i = 0; $i < 10; $i++) {
            $user = $this->odm->source(Admin::class)->create();
            $this->assertInstanceOf(Admin::class, $user);

            $user->name = 'John';
            $user->piece->value = $i;

            $user->pieces->add(new DataPiece(['value' => 900], $this->odm, null));

            $user->save();
        }

        $this->assertSame(20, $this->odm->source(User::class)->count());

        /**
         * @var User $user
         */
        $shift = 0;
        foreach ($this->odm->selector(User::class) as $user) {
            if ($shift < 10) {
                $this->assertInstanceOf(User::class, $user);
            } else {
                $this->assertInstanceOf(Admin::class, $user);
            }

            $shift++;
        }
    }

    public function testSelectWithQuery()
    {
        $this->assertSame(0, $this->odm->source(User::class)->count());

        for ($i = 0; $i < 10; $i++) {
            $user = $this->odm->source(User::class)->create();
            $this->assertInstanceOf(User::class, $user);

            $user->name = 'Anton';
            $user->piece->value = 100;
            $user->save();
        }

        for ($i = 0; $i < 10; $i++) {
            $user = $this->odm->source(Admin::class)->create();
            $this->assertInstanceOf(Admin::class, $user);

            $user->name = 'John';
            $user->piece->value = 200;

            $user->pieces->add(new DataPiece(['value' => $i], $this->odm, null));

            $user->save();
        }

        $this->assertSame(20, $this->odm->source(User::class)->count());

        $this->assertSame(10, $this->odm->source(User::class)->count(['piece.value' => '100']));
        foreach ($this->odm->source(User::class)->find(['piece.value' => '100']) as $user) {
            $this->assertInstanceOf(User::class, $user);
        }

        $this->assertSame(10, $this->odm->source(User::class)->count(['piece.value' => '200']));
        foreach ($this->odm->source(User::class)->find(['piece.value' => '200']) as $user) {
            $this->assertInstanceOf(Admin::class, $user);
        }

        $this->assertSame(10, $this->odm->source(User::class)->count(['admins' => 'all']));
        foreach ($this->odm->source(User::class)->find(['admins' => '200']) as $user) {
            $this->assertInstanceOf(Admin::class, $user);
        }

        $this->assertSame(
            10,
            $this->odm->source(User::class)->count(['pieces.value' => ['$exists' => 1]])
        );
        foreach ($this->odm->source(User::class)->find(['pieces.value' => ['$exists' => 1]]) as $user) {
            $this->assertInstanceOf(Admin::class, $user);
        }
    }

    public function testCursor()
    {
        $this->assertSame(0, $this->odm->source(User::class)->count());

        for ($i = 0; $i < 10; $i++) {
            $user = $this->odm->source(User::class)->create();
            $user->name = 'Anton';
            $user->piece->value = 100;
            $user->save();
        }

        $cursor = $this->odm->source(User::class)->find(['piece.value' => '100'])->getIterator();
        $this->assertInstanceOf(DocumentCursor::class, $cursor);
        $this->assertInstanceOf(Cursor::class, $cursor->getCursor());

        $cursor = $this->odm->source(User::class)->find(['piece.value' => '100'])->getIterator();
        $this->assertCount(10, $result = $cursor->toArray());

        foreach ($result as $user) {
            $this->assertInstanceOf(User::class, $user);
        }

        $cursor = $this->odm->source(User::class)->find(['piece.value' => '100'])->getIterator();
        $this->assertCount(10, $result = $cursor->fetchAll());

        foreach ($result as $user) {
            $this->assertInstanceOf(User::class, $user);
        }
    }
}