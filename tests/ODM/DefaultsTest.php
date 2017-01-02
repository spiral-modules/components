<?php
/**
 * Spiral Framework, Core Components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ODM;

use Spiral\ODM\ODM;
use Spiral\Tests\ODM\Fixtures\Admin;
use Spiral\Tests\ODM\Fixtures\BadRecursivePiece;
use Spiral\Tests\ODM\Fixtures\DataPiece;
use Spiral\Tests\ODM\Fixtures\RecursivePiece;
use Spiral\Tests\ODM\Fixtures\User;
use Spiral\Tests\ODM\Traits\ODMTrait;

class DefaultsTest extends \PHPUnit_Framework_TestCase
{
    use ODMTrait;

    public function testEmptyDefaults()
    {
        $builder = $this->makeBuilder();

        $odm = $this->makeODM();

        $builder->addSchema($this->makeSchema(User::class));
        $builder->addSchema($this->makeSchema(Admin::class));
        $odm->buildSchema($builder);

        $user = $odm->create(User::class, []);
        $this->assertInstanceOf(User::class, $user);

        $this->assertSame(null, $user->_id);
        $this->assertSame('', $user->name);
    }

    public function testUserDefaults()
    {
        $builder = $this->makeBuilder();
        $odm = $this->makeODM();

        $builder->addSchema($this->makeSchema(User::class));
        $builder->addSchema($this->makeSchema(Admin::class));
        $odm->buildSchema($builder);

        $admin = $odm->create(Admin::class, []);
        $this->assertInstanceOf(Admin::class, $admin);

        $this->assertSame(null, $admin->_id);
        $this->assertSame('', $admin->name);
        $this->assertSame('all', $admin->admins);
    }

    public function testCompositeDefaults()
    {
        $builder = $this->makeBuilder();

        $builder->addSchema($this->makeSchema(User::class));
        $builder->addSchema($this->makeSchema(Admin::class));
        $builder->addSchema($this->makeSchema(DataPiece::class));

        $schema = $builder->packSchema();

        $userDefaults = $schema[User::class][ODM::D_SCHEMA][User::SH_DEFAULTS];
        $adminDefaults = $schema[Admin::class][ODM::D_SCHEMA][User::SH_DEFAULTS];

        $this->assertNotSame($userDefaults, $adminDefaults);

        $this->assertEquals(['value' => '', 'something' => 0], $userDefaults['piece']);
        $this->assertEquals(['value' => 'admin-value', 'something' => 0], $adminDefaults['piece']);
    }

    public function testCompositeDefaultsMissingClasses()
    {
        $builder = $this->makeBuilder();
        $builder->addSchema($this->makeSchema(User::class));
        $schema = $builder->packSchema();

        $userDefaults = $schema[User::class][ODM::D_SCHEMA][User::SH_DEFAULTS];

        $this->assertNull($userDefaults['piece']);
    }

    public function testRecursiveDefaults()
    {
        $builder = $this->makeBuilder();

        $builder->addSchema($this->makeSchema(RecursivePiece::class));

        $schema = $builder->packSchema();

        $defaults = $schema[RecursivePiece::class][ODM::D_SCHEMA][User::SH_DEFAULTS];
        $this->assertNull($defaults['child']);
    }

    /**
     * @expectedException \Spiral\ODM\Exceptions\SchemaException
     * @expectedExceptionMessage Possible recursion issue in
     *                           'Spiral\Tests\ODM\Fixtures\BadRecursivePiece', model refers to
     *                           itself (has default value)
     */
    public function testRecursiveDefaultsWithException()
    {
        $builder = $this->makeBuilder();

        $builder->addSchema($this->makeSchema(BadRecursivePiece::class));

        $builder->packSchema();
    }

    //todo: test accessors?
}