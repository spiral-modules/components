<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ODM;

use Spiral\Models\Reflections\ReflectionEntity as RE;
use Spiral\ODM\Schemas\DocumentSchema as DS;
use Spiral\Tests\ODM\Fixtures\Admin;
use Spiral\Tests\ODM\Fixtures\User;
use Spiral\Tests\ODM\Traits\ODMTrait;

class DefaultsTest extends \PHPUnit_Framework_TestCase
{
    use ODMTrait;

    public function testEmptyDefaults()
    {
        $builder = $this->makeBuilder();
        $mutators = $this->mutatorsConfig();
        $odm = $this->makeODM();

        $builder->addSchema(new DS(new RE(User::class), $mutators));
        $builder->addSchema(new DS(new RE(Admin::class), $mutators));
        $odm->setSchema($builder);

        $user = $odm->instantiate(User::class, []);
        $this->assertInstanceOf(User::class, $user);

        $this->assertSame(null, $user->_id);
        $this->assertSame('', $user->name);
    }

    public function testUserDefaults()
    {
        $builder = $this->makeBuilder();
        $mutators = $this->mutatorsConfig();
        $odm = $this->makeODM();

        $builder->addSchema(new DS(new RE(User::class), $mutators));
        $builder->addSchema(new DS(new RE(Admin::class), $mutators));
        $odm->setSchema($builder);

        $admin = $odm->instantiate(Admin::class, []);
        $this->assertInstanceOf(Admin::class, $admin);

        $this->assertSame(null, $admin->_id);
        $this->assertSame('', $admin->name);
        $this->assertSame('all', $admin->admins);
    }

    //todo: test composited models
    //todo: test accessors?
}