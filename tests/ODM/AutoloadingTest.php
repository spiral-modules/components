<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */
namespace Spiral\Tests\ODM;

use Mockery as m;
use Spiral\Core\Container;
use Spiral\Core\MemoryInterface;
use Spiral\ODM\MongoManager;
use Spiral\ODM\ODM;
use Spiral\ODM\Schemas\LocatorInterface;
use Spiral\ODM\Schemas\SchemaBuilder;
use Spiral\Tests\ODM\Fixtures\Admin;
use Spiral\Tests\ODM\Fixtures\Moderator;
use Spiral\Tests\ODM\Fixtures\User;
use Spiral\Tests\ODM\Traits\ODMTrait;

class AutoloadingTest extends \PHPUnit_Framework_TestCase
{
    use ODMTrait;

    public function testAutolocationOfDocuments()
    {
        $memory = m::mock(MemoryInterface::class);
        $memory->shouldReceive('loadData')->with(ODM::MEMORY)->andReturn([
            User::class => [
                ODM::D_DATABASE => 'user-database'
            ]
        ]);

        $locator = m::mock(LocatorInterface::class);

        $odm = new ODM(
            m::mock(MongoManager::class),
            $locator,
            $memory,
            $container = new Container()
        );
        $this->assertSame('user-database', $odm->define(User::class, ODM::D_DATABASE));

        $builder = new SchemaBuilder(m::mock(MongoManager::class));
        $container->bind(SchemaBuilder::class, $builder);

        $locator->shouldNotReceive('locateSchemas');
        $odmBuilder = $odm->schemaBuilder(false);
        $this->assertSame($odmBuilder, $builder);

        $locator->shouldReceive('locateSchemas')->andReturn([
            $sA = $this->makeSchema(User::class),
            $sB = $this->makeSchema(Admin::class)
        ]);

        $locator->shouldReceive('locateSources')->andReturn([]);

        $odmBuilder = $odm->schemaBuilder(true);
        $this->assertSame($odmBuilder, $builder);

        $this->assertCount(2, $builder->getSchemas());

        $this->assertTrue($builder->hasSchema(User::class));
        $this->assertTrue($builder->hasSchema(Admin::class));
        $this->assertFalse($builder->hasSchema(Moderator::class));

        $this->assertContains($sA, $builder->getSchemas());
        $this->assertContains($sB, $builder->getSchemas());
    }
}