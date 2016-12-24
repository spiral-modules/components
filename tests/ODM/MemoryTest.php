<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ODM;

use Mockery as m;
use Spiral\Core\Container;
use Spiral\Core\MemoryInterface;
use Spiral\ODM\MongoManager;
use Spiral\ODM\ODM;
use Spiral\ODM\Schemas\SchemaBuilder;
use Spiral\ODM\Schemas\SchemaLocator;
use Spiral\Tests\ODM\Fixtures\User;
use Spiral\Tests\ODM\Traits\ODMTrait;

class MemoryTest extends \PHPUnit_Framework_TestCase
{
    use ODMTrait;

    public function testLoadSchema()
    {
        $memory = m::mock(MemoryInterface::class);

        $memory->shouldReceive('loadData')->with(ODM::MEMORY)->andReturn([
            User::class => [
                ODM::D_DATABASE => 'user-database'
            ]
        ]);

        $odm = new ODM(
            m::mock(MongoManager::class),
            m::mock(SchemaLocator::class),
            $memory,
            new Container()
        );

        $this->assertSame('user-database', $odm->define(User::class, ODM::D_DATABASE));
    }

    public function testSetSchema()
    {
        $memory = m::mock(MemoryInterface::class);

        $memory->shouldReceive('loadData')->with(ODM::MEMORY)->andReturn([
            User::class => [
                ODM::D_DATABASE => 'user-database'
            ]
        ]);

        $odm = new ODM(
            m::mock(MongoManager::class),
            m::mock(SchemaLocator::class),
            $memory,
            new Container()
        );

        $this->assertSame('user-database', $odm->define(User::class, ODM::D_DATABASE));

        $builder = m::mock(SchemaBuilder::class);

        $builder->shouldReceive('packSchema')->andReturn([
            User::class => [
                ODM::D_DATABASE => 'new-database'
            ]
        ]);

        $memory->shouldNotReceive('saveData');

        $odm->setSchema($builder, false);
        $this->assertSame('new-database', $odm->define(User::class, ODM::D_DATABASE));
    }
}