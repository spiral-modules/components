<?php
/**
 * Spiral Framework, Core Components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ODM;

use Mockery as m;
use Spiral\ODM\DocumentEntity;
use Spiral\ODM\Entities\DocumentCompositor;
use Spiral\ODM\Schemas\Definitions\CompositionDefinition;
use Spiral\Tests\ODM\Fixtures\Admin;
use Spiral\Tests\ODM\Fixtures\DataPiece;
use Spiral\Tests\ODM\Fixtures\User;
use Spiral\Tests\ODM\Traits\ODMTrait;

class CompositeManyTest extends \PHPUnit_Framework_TestCase
{
    use ODMTrait;

    public function testCompositeMany()
    {
        $builder = $this->makeBuilder();
        $builder->addSchema($user = $this->makeSchema(User::class));
        $builder->addSchema($admin = $this->makeSchema(Admin::class));
        $builder->addSchema($this->makeSchema(DataPiece::class));

        //No data piece been registered
        $this->assertEquals([
            'piece'  => new CompositionDefinition(DocumentEntity::ONE, DataPiece::class),
            'pieces' => new CompositionDefinition(DocumentEntity::MANY, DataPiece::class),
        ], $admin->getCompositions($builder));

        $odm = $this->makeODM();
        $odm->buildSchema($builder);

        $admin = $odm->make(Admin::class);

        $this->assertInstanceOf(User::class, $admin);
        $this->assertInstanceOf(DocumentCompositor::class, $admin->pieces);
    }

    public function testCompositeOneWithValue()
    {
        $builder = $this->makeBuilder();
        $builder->addSchema($this->makeSchema(User::class));
        $builder->addSchema($admin = $this->makeSchema(Admin::class));
        $builder->addSchema($this->makeSchema(DataPiece::class));

        $odm = $this->makeODM();
        $odm->buildSchema($builder);

        $admin = $odm->make(Admin::class, ['pieces' => [['value' => 'abc']]]);

        $this->assertCount(1, $admin->pieces);
    }

    public function testCompositeHasByQuery()
    {
        $builder = $this->makeBuilder();
        $builder->addSchema($this->makeSchema(User::class));
        $builder->addSchema($admin = $this->makeSchema(Admin::class));
        $builder->addSchema($this->makeSchema(DataPiece::class));

        $odm = $this->makeODM();
        $odm->buildSchema($builder);

        $admin = $odm->make(Admin::class, ['pieces' => [['value' => 'abc']]]);

        $this->assertCount(1, $admin->pieces);
        $this->assertTrue($admin->pieces->has(['value' => 'abc']));
    }


    public function testCompositeHasByEntity()
    {
        $builder = $this->makeBuilder();
        $builder->addSchema($this->makeSchema(User::class));
        $builder->addSchema($admin = $this->makeSchema(Admin::class));
        $builder->addSchema($this->makeSchema(DataPiece::class));

        $odm = $this->makeODM();
        $odm->buildSchema($builder);

        $admin = $odm->make(Admin::class, ['pieces' => [['value' => 'abc']]]);

        $this->assertCount(1, $admin->pieces);
        $this->assertTrue($admin->pieces->has(
            $odm->make(DataPiece::class, ['value' => 'abc'])
        ));
    }

    public function testCompositeFindOneByQuery()
    {
        $builder = $this->makeBuilder();
        $builder->addSchema($this->makeSchema(User::class));
        $builder->addSchema($admin = $this->makeSchema(Admin::class));
        $builder->addSchema($this->makeSchema(DataPiece::class));

        $odm = $this->makeODM();
        $odm->buildSchema($builder);

        $admin = $odm->make(Admin::class, ['pieces' => [['value' => 'abc']]]);

        $this->assertCount(1, $admin->pieces);
        $this->assertInstanceOf(DataPiece::class, $admin->pieces->findOne(['value' => 'abc']));
        $this->assertEquals(
            $odm->make(DataPiece::class, ['value' => 'abc'])->packValue(),
            $admin->pieces->findOne(['value' => 'abc'])->packValue()
        );
    }

    public function testCompositeFindOneByEntity()
    {
        $builder = $this->makeBuilder();
        $builder->addSchema($this->makeSchema(User::class));
        $builder->addSchema($admin = $this->makeSchema(Admin::class));
        $builder->addSchema($this->makeSchema(DataPiece::class));

        $odm = $this->makeODM();
        $odm->buildSchema($builder);

        $admin = $odm->make(Admin::class, ['pieces' => [['value' => 'abc']]]);

        $this->assertCount(1, $admin->pieces);
        $entity = $odm->make(DataPiece::class, ['value' => 'abc']);

        $this->assertInstanceOf(DataPiece::class, $admin->pieces->findOne($entity));
        $this->assertEquals(
            $entity->packValue(),
            $admin->pieces->findOne($entity)->packValue()
        );
    }

    public function testCompositeSetValue()
    {
        $builder = $this->makeBuilder();
        $builder->addSchema($this->makeSchema(User::class));
        $builder->addSchema($admin = $this->makeSchema(Admin::class));
        $builder->addSchema($this->makeSchema(DataPiece::class));

        $odm = $this->makeODM();
        $odm->buildSchema($builder);

        $admin = $odm->make(Admin::class, ['pieces' => [['value' => 'abc']]]);

        $this->assertCount(1, $admin->pieces);

        $entity1 = $odm->make(DataPiece::class, ['value' => 'abc1']);
        $entity2 = $odm->make(DataPiece::class, ['value' => 'abc2']);

        $admin->pieces = [$entity1, $entity2];

        $this->assertCount(2, $admin->pieces);
        $this->assertFalse($admin->pieces->has(['value' => 'abc']));

        $this->assertTrue($admin->pieces->has(['value' => 'abc1']));
        $this->assertTrue($admin->pieces->has(['value' => 'abc2']));
    }

    public function testCompositeSetValueReLink()
    {
        $builder = $this->makeBuilder();
        $builder->addSchema($this->makeSchema(User::class));
        $builder->addSchema($admin = $this->makeSchema(Admin::class));
        $builder->addSchema($this->makeSchema(DataPiece::class));

        $odm = $this->makeODM();
        $odm->buildSchema($builder);

        $admin = $odm->make(Admin::class, ['pieces' => [['value' => 'abc']]]);

        $this->assertCount(1, $admin->pieces);

        $entity1 = $odm->make(DataPiece::class, ['value' => 'abc1']);
        $entity2 = $odm->make(DataPiece::class, ['value' => 'abc2']);

        $admin->pieces = [$entity1, $entity2];

        $this->assertCount(2, $admin->pieces);
        $this->assertFalse($admin->pieces->has(['value' => 'abc']));

        $this->assertTrue($admin->pieces->has(['value' => 'abc1']));
        $this->assertTrue($admin->pieces->has(['value' => 'abc2']));

        $this->assertNotSame($entity1, $admin->pieces->findOne($entity1));
        $this->assertNotSame($entity2, $admin->pieces->findOne($entity2));

        //But data must match
        $this->assertSame($entity1->packValue(), $admin->pieces->findOne($entity1)->packValue());
        $this->assertSame($entity2->packValue(), $admin->pieces->findOne($entity2)->packValue());

        $entity1->value = 'abc3';
        $entity2->value = 'abc4';

        //But not now
        $this->assertFalse($admin->pieces->has($entity1));
        $this->assertTrue($admin->pieces->has(['value' => 'abc1']));

        $this->assertFalse($admin->pieces->has($entity2));
        $this->assertTrue($admin->pieces->has(['value' => 'abc2']));
    }

    public function testCompositeSetValueByArray()
    {
        $builder = $this->makeBuilder();
        $builder->addSchema($this->makeSchema(User::class));
        $builder->addSchema($admin = $this->makeSchema(Admin::class));
        $builder->addSchema($this->makeSchema(DataPiece::class));

        $odm = $this->makeODM();
        $odm->buildSchema($builder);

        $admin = $odm->make(Admin::class, ['pieces' => [['value' => 'abc']]]);

        $this->assertCount(1, $admin->pieces);

        $admin->pieces = [
            ['value' => 123],
            ['value' => 456]
        ];

        $this->assertCount(2, $admin->pieces);

        $this->assertTrue($admin->pieces->has(['value' => 123]));
        $this->assertTrue($admin->pieces->has(['value' => 456]));

        //Typecasting
        $this->assertSame('123', $admin->pieces->findOne(['value' => 123])->value);
        $this->assertSame('456', $admin->pieces->findOne(['value' => 456])->value);
    }

    public function testFind()
    {
        $builder = $this->makeBuilder();
        $builder->addSchema($this->makeSchema(User::class));
        $builder->addSchema($admin = $this->makeSchema(Admin::class));
        $builder->addSchema($this->makeSchema(DataPiece::class));

        $odm = $this->makeODM();
        $odm->buildSchema($builder);

        $admin = $odm->make(Admin::class, ['pieces' => [['value' => 'abc']]]);

        $this->assertCount(1, $admin->pieces);
        $this->assertCount(1, $admin->pieces->find([]));
        $this->assertCount(1, $admin->pieces->find(['value' => 'abc']));
        $this->assertInstanceOf(DataPiece::class, $admin->pieces->find(['value' => 'abc'])[0]);
    }

    public function testFindOne()
    {
        $builder = $this->makeBuilder();
        $builder->addSchema($this->makeSchema(User::class));
        $builder->addSchema($admin = $this->makeSchema(Admin::class));
        $builder->addSchema($this->makeSchema(DataPiece::class));

        $odm = $this->makeODM();
        $odm->buildSchema($builder);

        $admin = $odm->make(Admin::class, ['pieces' => [['value' => 'abc']]]);

        $this->assertCount(1, $admin->pieces);
        $this->assertInstanceOf(DataPiece::class, $admin->pieces->findOne(['value' => 'abc']));
    }

    public function testPullOne()
    {
        $builder = $this->makeBuilder();
        $builder->addSchema($this->makeSchema(User::class));
        $builder->addSchema($admin = $this->makeSchema(Admin::class));
        $builder->addSchema($this->makeSchema(DataPiece::class));

        $odm = $this->makeODM();
        $odm->buildSchema($builder);

        $admin = $odm->make(Admin::class, ['pieces' => [['value' => 'abc']]]);

        $this->assertCount(1, $admin->pieces);

        $admin->pieces = [
            ['value' => 123],
            ['value' => 456],
            ['value' => 123],
            ['value' => '888']
        ];

        $this->assertTrue($admin->pieces->has(['value' => 888]));

        $this->assertCount(4, $admin->pieces);
        $admin->pieces->pull($odm->make(DataPiece::class, ['value' => 888]));
        $this->assertCount(3, $admin->pieces);

        $this->assertFalse($admin->pieces->has(['value' => 888]));
    }

    public function testPullMultiple()
    {
        $builder = $this->makeBuilder();
        $builder->addSchema($this->makeSchema(User::class));
        $builder->addSchema($admin = $this->makeSchema(Admin::class));
        $builder->addSchema($this->makeSchema(DataPiece::class));

        $odm = $this->makeODM();
        $odm->buildSchema($builder);

        $admin = $odm->make(Admin::class, ['pieces' => [['value' => 'abc']]]);

        $this->assertCount(1, $admin->pieces);

        $admin->pieces = [
            ['value' => 123],
            ['value' => 456],
            ['value' => 123],
            ['value' => '888']
        ];

        $this->assertTrue($admin->pieces->has(['value' => 888]));
        $this->assertTrue($admin->pieces->has(['value' => 123]));

        $this->assertCount(4, $admin->pieces);
        $admin->pieces->pull($odm->make(DataPiece::class, ['value' => 888]));

        //Must pull 2 entities
        $admin->pieces->pull($odm->make(DataPiece::class, ['value' => 123]));
        $this->assertCount(1, $admin->pieces);

        $this->assertFalse($admin->pieces->has(['value' => 123]));
        $this->assertFalse($admin->pieces->has(['value' => 888]));
    }

    public function testPushOne()
    {
        $builder = $this->makeBuilder();
        $builder->addSchema($this->makeSchema(User::class));
        $builder->addSchema($admin = $this->makeSchema(Admin::class));
        $builder->addSchema($this->makeSchema(DataPiece::class));

        $odm = $this->makeODM();
        $odm->buildSchema($builder);

        $admin = $odm->make(Admin::class, ['pieces' => [['value' => 'abc']]]);
        $this->assertCount(1, $admin->pieces);

        $admin->pieces->push($odm->make(DataPiece::class, ['value' => 888]));
        $this->assertCount(2, $admin->pieces);

        $this->assertTrue($admin->pieces->has(['value' => 888]));
    }

    public function testPushMultiple()
    {
        $builder = $this->makeBuilder();
        $builder->addSchema($this->makeSchema(User::class));
        $builder->addSchema($admin = $this->makeSchema(Admin::class));
        $builder->addSchema($this->makeSchema(DataPiece::class));

        $odm = $this->makeODM();
        $odm->buildSchema($builder);

        $admin = $odm->make(Admin::class, ['pieces' => [['value' => 'abc']]]);
        $this->assertCount(1, $admin->pieces);

        //Dupes are allowed by push
        $admin->pieces->push($odm->make(DataPiece::class, ['value' => 888]));
        $admin->pieces->push($odm->make(DataPiece::class, ['value' => 888]));
        $admin->pieces->push($odm->make(DataPiece::class, ['value' => 333]));

        $this->assertCount(4, $admin->pieces);

        $this->assertCount(2, $admin->pieces->find(['value' => 888]));
        $this->assertTrue($admin->pieces->has(['value' => 888]));
        $this->assertTrue($admin->pieces->has(['value' => 333]));
    }

    public function testAddOne()
    {
        $builder = $this->makeBuilder();
        $builder->addSchema($this->makeSchema(User::class));
        $builder->addSchema($admin = $this->makeSchema(Admin::class));
        $builder->addSchema($this->makeSchema(DataPiece::class));

        $odm = $this->makeODM();
        $odm->buildSchema($builder);

        $admin = $odm->make(Admin::class, ['pieces' => [['value' => 'abc']]]);
        $this->assertCount(1, $admin->pieces);

        $admin->pieces->add($odm->make(DataPiece::class, ['value' => 888]));
        $this->assertCount(2, $admin->pieces);

        $this->assertTrue($admin->pieces->has(['value' => 888]));
    }

    public function testAddMultiple()
    {
        $builder = $this->makeBuilder();
        $builder->addSchema($this->makeSchema(User::class));
        $builder->addSchema($admin = $this->makeSchema(Admin::class));
        $builder->addSchema($this->makeSchema(DataPiece::class));

        $odm = $this->makeODM();
        $odm->buildSchema($builder);

        $admin = $odm->make(Admin::class, ['pieces' => [['value' => 'abc']]]);
        $this->assertCount(1, $admin->pieces);

        //Dupes are allowed by push
        $admin->pieces->add($odm->make(DataPiece::class, ['value' => 888]));
        $admin->pieces->add($odm->make(DataPiece::class, ['value' => 888]));
        $admin->pieces->add($odm->make(DataPiece::class, ['value' => 333]));

        $this->assertCount(3, $admin->pieces);

        $this->assertCount(1, $admin->pieces->find(['value' => 888]));
        $this->assertTrue($admin->pieces->has(['value' => 888]));
        $this->assertTrue($admin->pieces->has(['value' => 333]));
    }
}