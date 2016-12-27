<?php
/**
 * Spiral Framework, Core Components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ODM;

use MongoDB\BSON\ObjectID;
use Spiral\ODM\DocumentEntity;
use Spiral\ODM\ODM;
use Spiral\Tests\ODM\Fixtures\Accessed;
use Spiral\Tests\ODM\Fixtures\Admin;
use Spiral\Tests\ODM\Fixtures\DataPiece;
use Spiral\Tests\ODM\Fixtures\User;
use Spiral\Tests\ODM\Traits\ODMTrait;

//Note, there is no need to test save operations in this trait, see ActiveRecordTest
class AtomicsTest extends \PHPUnit_Framework_TestCase
{
    use ODMTrait;

    /**
     * @var ODM
     */
    private $odm;

    public function setUp()
    {
        $this->odm = $this->makeODM();

        $builder = $this->makeBuilder();
        $builder->addSchema($this->makeSchema(User::class));
        $builder->addSchema($this->makeSchema(Admin::class));
        $builder->addSchema($this->makeSchema(DataPiece::class));
        $builder->addSchema($this->makeSchema(Accessed::class));

        $this->odm->setSchema($builder);
    }

    public function testDirtyFields()
    {
        $entity = $this->makeLoaded(Accessed::class);
        $entity->name = 'new-name';

        $this->assertSame([
            '$set' => ['name' => 'new-name']
        ], $entity->buildAtomics());
    }

    public function testSolidState()
    {
        $entity = $this->makeLoaded(Accessed::class);
        $entity->solidState(true);
        $entity->name = 'new-name';

        $this->assertSame([
            '$set' => [
                'name'       => 'new-name',
                'tags'       => [],
                'relatedIDs' => []
            ]
        ], $entity->buildAtomics());
    }

    public function testArrayAtomicsPull()
    {
        $entity = $this->makeLoaded(Accessed::class);
        $entity->name = 'new-name';
        $entity->tags->pull('tag');

        $this->assertSame([
            '$set'  => [
                'name' => 'new-name',
            ],
            '$pull' => [
                'tags' => ['$in' => ['tag']]
            ]
        ], $entity->buildAtomics());
    }

    public function testArrayAtomicsPush()
    {
        $entity = $this->makeLoaded(Accessed::class);
        $entity->name = 'new-name';
        $entity->tags->push('tag');

        $this->assertSame([
            '$set'  => [
                'name' => 'new-name',
            ],
            '$push' => [
                'tags' => ['$each' => ['tag']]
            ]
        ], $entity->buildAtomics());
    }

    public function testArrayAtomicsAdd()
    {
        $entity = $this->makeLoaded(Accessed::class);
        $entity->name = 'new-name';
        $entity->tags->add('tag');

        $this->assertSame([
            '$set'      => [
                'name' => 'new-name',
            ],
            '$addToSet' => [
                'tags' => ['$each' => ['tag']]
            ]
        ], $entity->buildAtomics());
    }

    public function testArrayAtomicsAddButArrayIsSolid()
    {
        $entity = $this->makeLoaded(Accessed::class);
        $entity->name = 'new-name';
        $entity->tags->add('tag');
        $entity->tags->solidState(true);

        $this->assertSame([
            '$set' => [
                'name' => 'new-name',
                'tags' => ['tag']
            ]
        ], $entity->buildAtomics());
    }

    public function testArrayAtomicsAddButParentIsSolid()
    {
        $entity = $this->makeLoaded(Accessed::class);
        $entity->solidState(true);

        $entity->name = 'new-name';
        $entity->tags->add('tag');

        $this->assertSame([
            '$set' => [
                'name'       => 'new-name',
                'tags'       => ['tag'],
                'relatedIDs' => []
            ]
        ], $entity->buildAtomics());
    }

    public function testMultipleAtomicsSetFallback()
    {
        $entity = $this->makeLoaded(Accessed::class);
        $entity->name = 'new-name';
        $entity->tags->add('tag');
        $entity->tags->add('tag2');
        $entity->tags->add('tag3');
        $entity->tags->pull('tag');

        $this->assertSame([
            '$set' => [
                'name' => 'new-name',
                'tags' => ['tag2', 'tag3']
            ]
        ], $entity->buildAtomics());
    }

    public function testNewArray()
    {
        $entity = $this->makeLoaded(Accessed::class);
        $entity->name = 'new-name';
        $entity->tags->add('tag');
        $entity->tags->add('tag2');
        $entity->tags->add('tag3');
        $entity->tags->pull('tag');

        $entity->tags = ['a', 'b', 'c'];

        $this->assertSame([
            '$set' => [
                'name' => 'new-name',
                'tags' => ['a', 'b', 'c']
            ]
        ], $entity->buildAtomics());
    }

    public function testNestedChanged()
    {
        $entity = $this->makeLoaded(User::class);
        $entity->name = 'new-name';
        $entity->piece->value = 'abc';

        $this->assertSame([
            '$set' => [
                'name'        => 'new-name',
                'piece.value' => 'abc'
            ]
        ], $entity->buildAtomics());
    }

    public function testNestedChangedButSolid()
    {
        $entity = $this->makeLoaded(User::class);
        $entity->name = 'new-name';
        $entity->piece->value = 'abc';

        $entity->piece->solidState(true);

        $this->assertSame([
            '$set' => [
                'name'  => 'new-name',
                'piece' => [
                    'value'     => 'abc',
                    'something' => 0
                ]
            ]
        ], $entity->buildAtomics());
    }

    public function testNestedChangedButParentSolid()
    {
        $entity = $this->makeLoaded(User::class);
        $entity->name = 'new-name';
        $entity->piece->value = 'abc';

        $entity->solidState(true);

        $this->assertSame([
            '$set' => [
                'name'  => 'new-name',
                'piece' => [
                    'value'     => 'abc',
                    'something' => 0
                ]
            ]
        ], $entity->buildAtomics());
    }

    public function testSetNewEntity()
    {
        $entity = $this->makeLoaded(User::class);
        $entity->name = 'new-name';
        $entity->piece->value = 'abc';

        $piece = $this->makeNew(DataPiece::class, ['value' => 'new-value']);

        //To make test harder
        $piece->flushUpdates();

        $entity->piece = $piece;

        $this->assertSame([
            '$set' => [
                'name'  => 'new-name',
                'piece' => [
                    'value'     => 'new-value',
                    'something' => 0
                ]
            ]
        ], $entity->buildAtomics());
    }

    public function testCompositionPush()
    {
        $entity = $this->makeLoaded(Admin::class);
        $entity->pieces->push($this->makeNew(DataPiece::class, ['something' => 1]));
        $entity->pieces->push($this->makeNew(DataPiece::class, ['something' => 2]));

        $this->assertSame([
            '$push' => [
                'pieces' => [
                    '$each' => [
                        ['value' => '', 'something' => 1],
                        ['value' => '', 'something' => 2]
                    ]
                ]
            ]
        ], $entity->buildAtomics());
    }

    public function testCompositionAdd()
    {
        $entity = $this->makeLoaded(Admin::class);
        $entity->pieces->add($this->makeNew(DataPiece::class, ['something' => 1]));
        $entity->pieces->add($this->makeNew(DataPiece::class, ['something' => 2]));

        $this->assertSame([
            '$addToSet' => [
                'pieces' => [
                    '$each' => [
                        ['value' => '', 'something' => 1],
                        ['value' => '', 'something' => 2]
                    ]
                ]
            ]
        ], $entity->buildAtomics());
    }

    public function testCompositionPull()
    {
        $entity = $this->makeLoaded(Admin::class);
        $entity->pieces->pull($this->makeNew(DataPiece::class, ['something' => 1]));

        $this->assertSame([
            '$pull' => [
                'pieces' => [
                    '$in' => [
                        ['value' => '', 'something' => 1],
                    ]
                ]
            ]
        ], $entity->buildAtomics());
    }

    public function testCompositionAddButSolid()
    {
        $entity = $this->makeLoaded(Admin::class);
        $entity->pieces->add($this->makeNew(DataPiece::class, ['something' => 1]));
        $entity->pieces->add($this->makeNew(DataPiece::class, ['something' => 2]));

        $entity->name = 'new-name';
        $entity->pieces->solidState(true);

        $this->assertSame([
            '$set' => [
                'name'   => 'new-name',
                'pieces' => [
                    ['value' => '', 'something' => 1],
                    ['value' => '', 'something' => 2]
                ]
            ]
        ], $entity->buildAtomics());
    }

    public function testCompositionAddButParentIsSolid()
    {
        $entity = $this->makeLoaded(Admin::class);
        $entity->pieces->add($this->makeNew(DataPiece::class, ['something' => 1]));
        $entity->pieces->add($this->makeNew(DataPiece::class, ['something' => 2]));

        $entity->name = 'new-name';
        $entity->solidState(true);

        $this->assertSame([
            '$set' => [
                'name'   => 'new-name',
                'piece'  => ['value' => 'admin-value', 'something' => 0],
                'admins' => 'all',
                'pieces' => [
                    ['value' => '', 'something' => 1],
                    ['value' => '', 'something' => 2]
                ]
            ]
        ], $entity->buildAtomics());
    }

    public function testCompositionMultipleOperationsFallback()
    {
        $entity = $this->makeLoaded(Admin::class);
        $entity->pieces->add($this->makeNew(DataPiece::class, ['something' => 1]));
        $entity->pieces->push($this->makeNew(DataPiece::class, ['something' => 2]));

        $entity->name = 'new-name';

        $this->assertSame([
            '$set' => [
                'name'   => 'new-name',
                'pieces' => [
                    ['value' => '', 'something' => 1],
                    ['value' => '', 'something' => 2]
                ]
            ]
        ], $entity->buildAtomics());
    }

    public function testCompositionAddButNestedFallack()
    {
        $entity = $this->makeLoaded(Admin::class);
        $entity->pieces->add($this->makeNew(DataPiece::class, ['something' => 1]));
        $entity->pieces->add($this->makeNew(DataPiece::class, ['something' => 2]));

        $entity->name = 'new-name';

        $entity->pieces->findOne(['something' => 1])->something = 3;

        $this->assertSame([
            '$set' => [
                'name'   => 'new-name',
                'pieces' => [
                    ['value' => '', 'something' => 3],
                    ['value' => '', 'something' => 2]
                ]
            ]
        ], $entity->buildAtomics());
    }

    public function testSingularNestedChange()
    {
        $entity = $this->makeLoaded(Admin::class, [
            'pieces' => [
                ['value' => '', 'something' => 1],
                ['value' => '', 'something' => 2]
            ]
        ]);

        $entity->name = 'new-name';
        $entity->pieces->findOne(['something' => 1])->something = 3;

        $this->assertSame([
            '$set' => [
                'name'   => 'new-name',
                'pieces' => [
                    ['value' => '', 'something' => 3],
                    ['value' => '', 'something' => 2]
                ]
            ]
        ], $entity->buildAtomics());
    }

    private function makeLoaded(string $class, $fields = []): DocumentEntity
    {
        return $this->odm->instantiate($class, [
                '_id' => new ObjectID('507f1f77bcf86cd799439011')
            ] + $fields, false);
    }

    private function makeNew(string $class, $fields = []): DocumentEntity
    {
        return $this->odm->instantiate($class, $fields);
    }
}