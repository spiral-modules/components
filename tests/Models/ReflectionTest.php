<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\tests\Cases\Models;

use Spiral\Models\Reflections\ReflectionEntity;
use Spiral\Models\SchematicEntity;

class ReflectionTest extends \PHPUnit_Framework_TestCase
{
    public function testReflection()
    {
        $schema = new ReflectionEntity(TestModel::class);
        $this->assertEquals(new \ReflectionClass(TestModel::class), $schema->getReflection());
    }

    public function testFillable()
    {
        $schema = new ReflectionEntity(TestModel::class);
        $this->assertSame(['value'], $schema->getFillable());
    }

    public function testFillableExtended()
    {
        $schema = new ReflectionEntity(ExtendedModel::class);
        $this->assertSame(['value', 'name'], $schema->getFillable());
    }

    public function testSetters()
    {
        $schema = new ReflectionEntity(TestModel::class);
        $this->assertSame(
            [
                'value' => 'intval'
            ],
            $schema->getSetters()
        );
    }

    public function testSettersExtended()
    {
        $schema = new ReflectionEntity(ExtendedModel::class);
        $this->assertSame(
            [
                'value' => 'intval',
                'name'  => 'strval'
            ],
            $schema->getSetters()
        );
    }

    public function testValidates()
    {
        $schema = new ReflectionEntity(TestModel::class);
        $this->assertSame(
            [
                'value' => ['notEmpty']
            ],
            $schema->getValidates()
        );
    }

    public function testValidatesExtended()
    {
        $schema = new ReflectionEntity(ExtendedModel::class);
        $this->assertSame(
            [
                'value' => ['notEmpty'],
                'name'  => ['notEmpty']
            ],
            $schema->getValidates()
        );
    }
}

class TestModel extends SchematicEntity
{
    protected $fillable = ['value'];

    protected $setters = ['value' => 'intval'];
    protected $getters = ['value' => 'intval'];

    protected $validates = [
        'value' => ['notEmpty']
    ];

}

class ExtendedModel extends TestModel
{
    protected $fillable = ['name'];

    protected $setters = ['name' => 'strval'];

    protected $getters = ['name' => 'strtoupper'];

    protected $validates = [
        'name' => ['notEmpty']
    ];
}