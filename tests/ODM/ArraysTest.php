<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ODM;

use MongoDB\BSON\ObjectID;
use Spiral\ODM\Accessors\IntegerArray;
use Spiral\ODM\Accessors\ObjectIDsArray;
use Spiral\ODM\Accessors\StringArray;
use Spiral\Tests\ODM\Fixtures\Arrayed;
use Spiral\Tests\ODM\Traits\ODMTrait;

class ArraysTest extends \PHPUnit_Framework_TestCase
{
    use ODMTrait;

    public function testArrayString()
    {
        $builder = $this->makeBuilder();
        $odm = $this->makeODM();

        $builder->addSchema($this->makeSchema(Arrayed::class));
        $odm->buildSchema($builder);

        $entity = $odm->make(Arrayed::class, []);
        $this->assertInstanceOf(Arrayed::class, $entity);

        $this->assertInstanceOf(StringArray::class, $entity->strings);

        $entity->strings->add(900);

        //Skipped
        $entity->strings->add([]);

        $this->assertSame([
            '1234',
            'test',
            '900'
        ], $entity->strings->packValue());
    }

    public function testNumericArray()
    {
        $builder = $this->makeBuilder();
        $odm = $this->makeODM();

        $builder->addSchema($this->makeSchema(Arrayed::class));
        $odm->buildSchema($builder);

        $entity = $odm->make(Arrayed::class, []);
        $this->assertInstanceOf(Arrayed::class, $entity);

        $this->assertInstanceOf(IntegerArray::class, $entity->numbers);

        $entity->numbers->add('870');
        $entity->numbers->add([]); //skipped
        $entity->numbers->add('hello'); //skipped

        $this->assertSame([
            1,
            2,
            3,
            870
        ], $entity->numbers->packValue());
    }

    public function testArrayObjectIDs()
    {
        $builder = $this->makeBuilder();
        $odm = $this->makeODM();

        $builder->addSchema($this->makeSchema(Arrayed::class));
        $odm->buildSchema($builder);

        $entity = $odm->make(Arrayed::class, []);
        $this->assertInstanceOf(Arrayed::class, $entity);

        $this->assertInstanceOf(ObjectIDsArray::class, $entity->ids);

        $entity->ids->add('4af9f23d8ead0e1d32000000');

        $this->assertEquals([
            new ObjectID('507f1f77bcf86cd799439011'),
            new ObjectID('4af9f23d8ead0e1d32000000'),
        ], $entity->ids->packValue());
    }
}