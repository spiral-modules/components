<?php
/**
 * Spiral Framework, Core Components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ODM;

use MongoDB\BSON\ObjectID;
use Spiral\Tests\ODM\Fixtures\User;
use Spiral\Tests\ODM\Traits\ODMTrait;

class MutatorsTest extends \PHPUnit_Framework_TestCase
{
    use ODMTrait;

    public function testSimpleFilter()
    {
        $builder = $this->makeBuilder();
        $odm = $this->makeODM();

        $builder->addSchema($this->makeSchema(User::class));
        $odm->buildSchema($builder);

        $user = $odm->create(User::class, []);
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('', $user->name);
    }

    public function testSimpleWithDefaultValue()
    {
        $builder = $this->makeBuilder();
        $odm = $this->makeODM();

        $builder->addSchema($this->makeSchema(User::class));
        $odm->buildSchema($builder);

        $user = $odm->create(User::class, ['name' => 'test']);
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('test', $user->name);
    }

    public function testTypecasting()
    {
        $builder = $this->makeBuilder();
        $odm = $this->makeODM();

        $builder->addSchema($this->makeSchema(User::class));
        $odm->buildSchema($builder);

        $user = $odm->create(User::class);
        $this->assertInstanceOf(User::class, $user);

        $user->name = 123;
        $this->assertInternalType('string', $user->name);
        $this->assertSame('123', $user->name);
    }

    public function testComplexFilter()
    {
        $builder = $this->makeBuilder();
        $odm = $this->makeODM();

        $builder->addSchema($this->makeSchema(User::class));
        $odm->buildSchema($builder);

        $user = $odm->create(User::class, []);
        $this->assertInstanceOf(User::class, $user);

        $user = $odm->create(User::class, ['name' => 'test']);
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('test', $user->name);

        $user->_id = '585d64a95a28e74be8004276';
        $this->assertInstanceOf(ObjectID::class, $user->_id);

        $this->assertEquals((string)new ObjectID('585d64a95a28e74be8004276'), (string)$user->_id);
    }
}