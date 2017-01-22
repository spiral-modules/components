<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
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
        $odm->buildSchema($builder);

        $user = $odm->make(User::class, []);
        $this->assertInstanceOf(User::class, $user);

        $user = $odm->make(User::class, ['name' => 'test']);
        $this->assertInstanceOf(User::class, $user);
    }

    public function testSimpleWithData()
    {
        $builder = $this->makeBuilder();
        $mutators = $this->mutatorsConfig();
        $odm = $this->makeODM();

        $builder->addSchema(new DS(new RE(User::class), $mutators));
        $odm->buildSchema($builder);

        $user = $odm->make(User::class, []);
        $this->assertInstanceOf(User::class, $user);

        $user = $odm->make(User::class, ['name' => 'test']);
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

        $odm->buildSchema($builder);

        $user = $odm->make(User::class, []);
        $this->assertInstanceOf(User::class, $user);

        $admin = $odm->make(Admin::class, []);
        $this->assertInstanceOf(Admin::class, $admin);

        //Auto-inheritance
        $admin = $odm->make(User::class, ['admins' => 'everything']);
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

        $odm->buildSchema($builder);

        $user = $odm->make(User::class, []);
        $this->assertInstanceOf(User::class, $user);

        $admin = $odm->make(Admin::class, []);
        $this->assertInstanceOf(Admin::class, $admin);


        $admin = $odm->make(User::class, ['admins' => 'everything']);
        $this->assertInstanceOf(Admin::class, $admin);

        $admin = $odm->make(User::class, ['admins' => 'everything', 'super' => 'yes']);
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

        $odm->buildSchema($builder);

        $user = $odm->make(User::class, []);
        $this->assertInstanceOf(User::class, $user);

        $admin = $odm->make(Admin::class, []);
        $this->assertInstanceOf(Admin::class, $admin);


        $admin = $odm->make(User::class, ['admins' => 'everything']);
        $this->assertInstanceOf(Admin::class, $admin);

        $admin = $odm->make(User::class, ['admins' => 'everything', 'super' => 'yes']);
        $this->assertInstanceOf(SuperAdministrator::class, $admin);
    }
}