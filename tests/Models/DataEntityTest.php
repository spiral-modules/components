<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\tests\Cases\Models;

use Spiral\Models\DataEntity;

//todo: improve test coverage
class DataEntityTest extends \PHPUnit_Framework_TestCase
{
    public function testSetter()
    {
        $entity = new DataEntity();
        $entity->setField('abc', 123);
        $this->assertEquals(123, $entity->getField('abc'));

        $this->assertTrue($entity->hasField('abc'));
        $this->assertFalse($entity->hasField('bce'));
    }

    public function testMagicProperties()
    {
        $entity = new DataEntity();
        $entity->abc = 123;
        $this->assertEquals(123, $entity->abc);

        $this->assertTrue(isset($entity->abc));
    }

    public function testMagicMethods()
    {
        $entity = new DataEntity();
        $entity->setAbc('123');
        $this->assertEquals(123, $entity->getAbc());
        $this->assertEquals($entity->getField('abc'), $entity->getAbc());

        $this->assertTrue($entity->hasField('abc'));
    }

    public function testSerialize()
    {
        $data = ['a' => 123, 'b' => null, 'c' => 'test'];

        $entity = new DataEntity($data);
        $this->assertEquals($data, $entity->serializeData());
    }
}
