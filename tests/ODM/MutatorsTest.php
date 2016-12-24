<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ODM;

use Spiral\Models\Reflections\ReflectionEntity as RE;
use Spiral\ODM\Schemas\DocumentSchema as DS;
use Spiral\Tests\ODM\Fixtures\User;
use Spiral\Tests\ODM\Traits\ODMTrait;

class MutatorsTest extends \PHPUnit_Framework_TestCase
{
    use ODMTrait;

    public function testSimplePassFilter()
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
    }

}