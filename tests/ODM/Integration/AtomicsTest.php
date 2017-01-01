<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ODM\Integration;

use Spiral\Tests\ODM\Fixtures\Admin;
use Spiral\Tests\ODM\Fixtures\DataPiece;
use Spiral\Tests\ODM\Fixtures\User;

/**
 * Only when MongoDatabase configured.
 */
class AtomicsTest extends BaseTest
{
    public function testDirtyFields()
    {
        $this->assertSame(0, $this->odm->source(User::class)->count());

        $user = $this->odm->source(Admin::class)->create();
        $this->assertInstanceOf(Admin::class, $user);

        $user->name = 'Test Admin';

        $this->assertSame([
            '$set' => [
                'name'   => 'Test Admin',
                'piece'  => [
                    'value'     => 'admin-value',
                    'something' => 0
                ],
                'admins' => 'all',
                'pieces' => []
            ]
        ], $user->buildAtomics());
        $user->save();

        $this->assertTrue($user->isLoaded());
        $this->assertTrue($user->isSolid());

        $user->admins = 'everywhere';
        $this->assertSame([
            '$set' => [
                'name'   => 'Test Admin',
                'piece'  => [
                    'value'     => 'admin-value',
                    'something' => 0
                ],
                'admins' => 'everywhere',
                'pieces' => []
            ]
        ], $user->buildAtomics());
        $user->save();

        $user->solidState(false);
        $this->assertFalse($user->isSolid());

        $user->admins = 'all';
        $this->assertSame([
            '$set' => [
                'admins' => 'all'
            ]
        ], $user->buildAtomics());
        $user->save();

        $user->piece->value = 123;
        $this->assertSame([
            '$set' => [
                'piece.value' => '123'
            ]
        ], $user->buildAtomics());
        $user->save();
    }

    public function testCompositeMany()
    {
        $this->assertSame(0, $this->odm->source(User::class)->count());

        $user = $this->odm->source(Admin::class)->create();
        $this->assertInstanceOf(Admin::class, $user);

        $user->name = 'Test Admin';
        $user->save();

        $this->assertTrue($user->isLoaded());
        $this->assertTrue($user->isSolid());

        $user->pieces->add($this->odm->instantiate(DataPiece::class, ['value' => 1]));

        $this->assertSame([
            '$set' => [
                'name'   => 'Test Admin',
                'piece'  => [
                    'value'     => 'admin-value',
                    'something' => 0
                ],
                'admins' => 'all',
                'pieces' => [
                    [
                        'value'     => '1',
                        'something' => 0
                    ]
                ]
            ]
        ], $user->buildAtomics());
        $user->save();

        $user->solidState(false);
        $user->pieces->add($this->odm->instantiate(DataPiece::class, ['value' => 2]));

        $this->assertSame([
            '$addToSet' => [
                'pieces' => [
                    '$each' => [
                        [
                            'value'     => '2',
                            'something' => 0
                        ]
                    ]
                ]
            ]
        ], $user->buildAtomics());
        $user->save();

        $this->assertCount(2, $user->pieces);
        $this->assertCount(2, $this->fromDB($user)->pieces);

        $user->pieces->add($this->odm->instantiate(DataPiece::class, ['value' => 2]));

        $this->assertSame([
            '$addToSet' => [
                'pieces' => [
                    '$each' => [
                        [
                            'value'     => '2',
                            'something' => 0
                        ]
                    ]
                ]
            ]
        ], $user->buildAtomics());
        $user->save();

        $this->assertCount(2, $user->pieces);
        $this->assertCount(2, $this->fromDB($user)->pieces);

        $user->pieces->push($this->odm->instantiate(DataPiece::class, ['value' => 2]));

        $this->assertSame([
            '$push' => [
                'pieces' => [
                    '$each' => [
                        [
                            'value'     => '2',
                            'something' => 0
                        ]
                    ]
                ]
            ]
        ], $user->buildAtomics());
        $user->save();

        $this->assertCount(3, $user->pieces);
        $this->assertCount(3, $this->fromDB($user)->pieces);

        $user->solidState(false);
        $user->pieces->pull($this->odm->instantiate(DataPiece::class, ['value' => 2]));

        $this->assertSame([
            '$pull' => [
                'pieces' => [
                    '$in' => [
                        [
                            'value'     => '2',
                            'something' => 0
                        ]
                    ]
                ]
            ]
        ], $user->buildAtomics());
        $user->save();

        $this->assertCount(1, $user->pieces);
        $this->assertCount(1, $this->fromDB($user)->pieces);
    }

    public function testCompositeManyParallel()
    {
        $this->assertSame(0, $this->odm->source(User::class)->count());

        $user = $this->odm->source(Admin::class)->create();
        $this->assertInstanceOf(Admin::class, $user);

        $user->name = 'Test Admin';
        $user->save();

        $this->assertSame(1, $this->odm->source(User::class)->count());

        $this->assertSame(
            'Test Admin',
            $this->odm->source(User::class)->findByPK($user->primaryKey())->name
        );
        $this->assertSame(
            [],
            $this->odm->source(User::class)->findByPK($user->primaryKey())->pieces->packValue()
        );

        //Now Magic begins
        $user1 = clone $user;
        $user2 = clone $user;

        $user->solidState(false);
        $user->pieces->add($this->odm->instantiate(DataPiece::class, ['value' => 1]));
        $user->save();

        $this->assertTrue($this->fromDB($user)->pieces->has(['value' => 1]));

        //This check
        $user1->solidState(false);
        $user1->pieces->push($this->odm->instantiate(DataPiece::class, ['value' => 2]));
        $user1->save();

        $this->assertTrue($this->fromDB($user)->pieces->has(['value' => 1]));
        $this->assertTrue($this->fromDB($user)->pieces->has(['value' => 2]));

        $user2->solidState(false);
        $user1->pieces->pull($this->odm->instantiate(DataPiece::class, ['value' => 1]));
        $user2->save();

        $this->assertFalse($this->fromDB($user)->pieces->has(['value' => 1]));
        $this->assertTrue($this->fromDB($user)->pieces->has(['value' => 2]));
    }
}