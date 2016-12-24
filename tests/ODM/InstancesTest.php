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
use Spiral\Tests\ODM\Fixtures\SuperAdministrator;
use Spiral\Tests\ODM\Fixtures\User;
use Spiral\Tests\ODM\Traits\ODMTrait;

class InstancesTest extends \PHPUnit_Framework_TestCase
{
    use ODMTrait;

    public function testSimple()
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
    }

    public function testSimpleWithData()
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

    public function testInheritance()
    {
        $builder = $this->makeBuilder();
        $mutators = $this->mutatorsConfig();
        $odm = $this->makeODM();

        $builder->addSchema(new DS(new RE(User::class), $mutators));
        $builder->addSchema(new DS(new RE(Admin::class), $mutators));

        $odm->setSchema($builder);

        $user = $odm->instantiate(User::class, []);
        $this->assertInstanceOf(User::class, $user);

        $admin = $odm->instantiate(Admin::class, []);
        $this->assertInstanceOf(Admin::class, $admin);

        //Auto-inheritance
        $admin = $odm->instantiate(User::class, ['admins' => 'everything']);
        $this->assertInstanceOf(Admin::class, $admin);
    }

    public function testDeepInheritance()
    {
        $builder = $this->makeBuilder();
        $mutators = $this->mutatorsConfig();
        $odm = $this->makeODM();

        $builder->addSchema(new DS(new RE(User::class), $mutators));
        $builder->addSchema(new DS(new RE(Admin::class), $mutators));
        $builder->addSchema(new DS(new RE(SuperAdministrator::class), $mutators));

        $odm->setSchema($builder);

        $user = $odm->instantiate(User::class, []);
        $this->assertInstanceOf(User::class, $user);

        $admin = $odm->instantiate(Admin::class, []);
        $this->assertInstanceOf(Admin::class, $admin);


        $admin = $odm->instantiate(User::class, ['admins' => 'everything']);
        $this->assertInstanceOf(Admin::class, $admin);
        
        $admin = $odm->instantiate(User::class, ['admins' => 'everything', 'super' => 'yes']);
        $this->assertInstanceOf(SuperAdministrator::class, $admin);
    }

    public function testDeepInheritanceDifferentOrder()
    {
        $builder = $this->makeBuilder();
        $mutators = $this->mutatorsConfig();
        $odm = $this->makeODM();

        $builder->addSchema(new DS(new RE(SuperAdministrator::class), $mutators));
        $builder->addSchema(new DS(new RE(User::class), $mutators));
        $builder->addSchema(new DS(new RE(Admin::class), $mutators));

        $odm->setSchema($builder);

        $user = $odm->instantiate(User::class, []);
        $this->assertInstanceOf(User::class, $user);

        $admin = $odm->instantiate(Admin::class, []);
        $this->assertInstanceOf(Admin::class, $admin);


        $admin = $odm->instantiate(User::class, ['admins' => 'everything']);
        $this->assertInstanceOf(Admin::class, $admin);

        $admin = $odm->instantiate(User::class, ['admins' => 'everything', 'super' => 'yes']);
        $this->assertInstanceOf(SuperAdministrator::class, $admin);
    }
}