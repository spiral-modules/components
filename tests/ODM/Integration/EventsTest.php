<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ODM\Integration;

use Spiral\Models\Events\EntityEvent;
use Spiral\ODM\Events\DocumentEvent;
use Spiral\Tests\ODM\Fixtures\User;

class EventsTest extends BaseTest
{
    public function testCreateAndCreated()
    {
        $this->assertSame(0, $this->odm->source(User::class)->count());

        $user = $this->odm->source(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $user->name = 'Anton';
        $user->piece->value = 123;

        $handled = [];
        $user::events()->addListener('create', function ($e) use (&$handled) {
            $this->assertInstanceOf(DocumentEvent::class, $e);
            $this->assertInstanceOf(EntityEvent::class, $e);
            $this->assertInstanceOf(User::class, $e->getEntity());

            $handled[] = 'create';
        });


        $user::events()->addListener('created', function ($e) use (&$handled) {
            $this->assertInstanceOf(DocumentEvent::class, $e);
            $this->assertInstanceOf(EntityEvent::class, $e);
            $this->assertInstanceOf(User::class, $e->getEntity());

            $handled[] = 'created';
        });


        $user->save();
        $this->assertSame(['create', 'created'], $handled);

        $this->assertSame(1, $this->odm->source(User::class)->count());
    }

    public function testUpdateAndUpdated()
    {
        $this->assertSame(0, $this->odm->source(User::class)->count());

        $user = $this->odm->source(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $user->name = 'Anton';
        $user->piece->value = 123;

        $user->save();

        $handled = [];
        $user::events()->addListener('update', function ($e) use (&$handled) {
            $this->assertInstanceOf(DocumentEvent::class, $e);
            $this->assertInstanceOf(EntityEvent::class, $e);
            $this->assertInstanceOf(User::class, $e->getEntity());

            $handled[] = 'update';
        });


        $user::events()->addListener('updated', function ($e) use (&$handled) {
            $this->assertInstanceOf(DocumentEvent::class, $e);
            $this->assertInstanceOf(EntityEvent::class, $e);
            $this->assertInstanceOf(User::class, $e->getEntity());

            $handled[] = 'updated';
        });

        $user->save();
        $this->assertSame(['update', 'updated'], $handled);

        $this->assertSame(1, $this->odm->source(User::class)->count());
    }

    public function testDeleteAndDeleted()
    {
        $this->assertSame(0, $this->odm->source(User::class)->count());

        $user = $this->odm->source(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $user->name = 'Anton';
        $user->piece->value = 123;

        $user->save();

        $handled = [];
        $user::events()->addListener('delete', function ($e) use (&$handled) {
            $this->assertInstanceOf(DocumentEvent::class, $e);
            $this->assertInstanceOf(EntityEvent::class, $e);
            $this->assertInstanceOf(User::class, $e->getEntity());

            $handled[] = 'delete';
        });


        $user::events()->addListener('deleted', function ($e) use (&$handled) {
            $this->assertInstanceOf(DocumentEvent::class, $e);
            $this->assertInstanceOf(EntityEvent::class, $e);
            $this->assertInstanceOf(User::class, $e->getEntity());

            $handled[] = 'deleted';
        });

        $user->delete();

        $this->assertSame(['delete', 'deleted'], $handled);

        $this->assertSame(0, $this->odm->source(User::class)->count());
    }
}