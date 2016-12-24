<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ODM;

use Spiral\ODM\Accessors\ObjectIDsArray;
use Spiral\ODM\Accessors\StringArray;
use Spiral\Tests\ODM\Fixtures\Accessed;
use Spiral\Tests\ODM\Traits\ODMTrait;

class AccessorsTest extends \PHPUnit_Framework_TestCase
{
    use ODMTrait;

    public function testAccessorConstruction()
    {
        $builder = $this->makeBuilder();
        $odm = $this->makeODM();

        $builder->addSchema($this->makeSchema(Accessed::class));
        $odm->setSchema($builder);

        $entity = $odm->instantiate(Accessed::class, []);
        $this->assertInstanceOf(Accessed::class, $entity);

        $this->assertInstanceOf(StringArray::class, $entity->tags);
        $this->assertInstanceOf(ObjectIDsArray::class, $entity->relatedIDs);
    }

    public function testAccessorWithDefaults()
    {
        $builder = $this->makeBuilder();
        $odm = $this->makeODM();

        $builder->addSchema($this->makeSchema(Accessed::class));
        $odm->setSchema($builder);

        $entity = $odm->instantiate(Accessed::class, [
            'tags' => ['a', 'b', 'c']
        ]);
        $this->assertInstanceOf(Accessed::class, $entity);

        $this->assertInstanceOf(StringArray::class, $entity->tags);
        $this->assertCount(3, $entity->tags);

        $this->assertTrue($entity->tags->has('a'));
        $this->assertTrue($entity->tags->has('b'));
        $this->assertTrue($entity->tags->has('c'));
        $this->assertFalse($entity->tags->has('d'));
    }
}