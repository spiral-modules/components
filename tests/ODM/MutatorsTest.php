<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ODM;

use MongoDB\BSON\ObjectID;
use Spiral\Models\Reflections\ReflectionEntity as RE;
use Spiral\ODM\Schemas\DocumentSchema as DS;
use Spiral\Tests\ODM\Fixtures\User;
use Spiral\Tests\ODM\Traits\ODMTrait;

class MutatorsTest extends \PHPUnit_Framework_TestCase
{
    use ODMTrait;

    public function testSimpleFilter()
    {
        $builder = $this->makeBuilder();
        $mutators = $this->mutatorsConfig();
        $odm = $this->makeODM();

        $builder->addSchema(new DS(new RE(User::class), $mutators));
        $odm->setSchema($builder);

        $user = $odm->instantiate(User::class, []);
        $this->assertInstanceOf(User::class, $user);

        $user = $odm->instantiate(User::class, ['name' => 'test']);
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('test', $user->name);

        $user->name = 123;
        $this->assertInternalType('string', $user->name);
    }

    public function testComplexFilter()
    {
        $builder = $this->makeBuilder();
        $mutators = $this->mutatorsConfig();
        $odm = $this->makeODM();

        $builder->addSchema(new DS(new RE(User::class), $mutators));
        $odm->setSchema($builder);

        $user = $odm->instantiate(User::class, []);
        $this->assertInstanceOf(User::class, $user);

        $user = $odm->instantiate(User::class, ['name' => 'test']);
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('test', $user->name);

        $user->_id = '585d64a95a28e74be8004276';
        $this->assertInstanceOf(ObjectID::class, $user->_id);
        $this->assertSame((string)new ObjectID('585d64a95a28e74be8004276'), (string)$user->_id);
    }
}