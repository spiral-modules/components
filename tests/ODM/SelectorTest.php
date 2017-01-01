<?php
/**
 * Spiral Framework, Core Components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ODM;

use Mockery as m;
use MongoDB\Collection;
use Spiral\Models\Reflections\ReflectionEntity as RE;
use Spiral\ODM\Entities\DocumentSelector;
use Spiral\ODM\Entities\MongoDatabase;
use Spiral\ODM\MongoManager;
use Spiral\ODM\Schemas\DocumentSchema as DS;
use Spiral\Tests\ODM\Fixtures\Admin;
use Spiral\Tests\ODM\Fixtures\ExternalDB;
use Spiral\Tests\ODM\Fixtures\Moderator;
use Spiral\Tests\ODM\Fixtures\SuperAdministrator;
use Spiral\Tests\ODM\Fixtures\SuperModerator;
use Spiral\Tests\ODM\Fixtures\User;
use Spiral\Tests\ODM\Traits\ODMTrait;

class SelectorTest extends \PHPUnit_Framework_TestCase
{
    use ODMTrait;

    public function testClass()
    {
        $selector = new DocumentSelector(
            m::mock(Collection::class),
            User::class,
            $this->initODM()
        );

        $this->assertSame(User::class, $selector->getClass());
    }

    public function testPaginatorTrait()
    {
        $selector = new DocumentSelector(
            m::mock(Collection::class),
            User::class,
            $this->initODM()
        );

        $this->assertSame(0, $selector->getOffset());
        $this->assertSame(0, $selector->getLimit());

        $selector->limit(10)->offset(12);

        $this->assertSame(12, $selector->getOffset());
        $this->assertSame(10, $selector->getLimit());
    }

    public function testFindOneNull()
    {
        $selector = new DocumentSelector(
            $collection = m::mock(Collection::class),
            User::class,
            $this->initODM()
        );

        $collection->shouldReceive('findOne')->with([
            'name'  => 'something',
            'value' => 'x'
        ], [
            'skip'    => 0,
            'limit'   => 0,
            'sort'    => [
                '_id' => -1
            ],
            'typeMap' => DocumentSelector::TYPE_MAP
        ])->andReturn(null);

        $result = $selector->where(['name' => 'something'])->sortBy(['_id' => -1])->findOne(
            ['value' => 'x']
        );

        $this->assertNull($result);
    }

    public function testFindOne()
    {
        $selector = new DocumentSelector(
            $collection = m::mock(Collection::class),
            User::class,
            $this->initODM()
        );

        $collection->shouldReceive('findOne')->with([
            'name'  => 'something',
            'value' => 'x'
        ], [
            'skip'    => 0,
            'limit'   => 0,
            'sort'    => [
                '_id' => -1
            ],
            'typeMap' => DocumentSelector::TYPE_MAP
        ])->andReturn([
            'name' => 'selected'
        ]);

        $result = $selector->where(['name' => 'something'])->sortBy(['_id' => -1])->findOne(
            ['value' => 'x']
        );

        $this->assertInstanceOf(User::class, $result);
        $this->assertSame('selected', $result->name);
    }

    public function testFindOneWithOffset()
    {
        $selector = new DocumentSelector(
            $collection = m::mock(Collection::class),
            User::class,
            $this->initODM()
        );

        $collection->shouldReceive('findOne')->with([
            'name'  => 'something',
            'value' => 'x'
        ], [
            'skip'    => 1,
            'limit'   => 0,
            'sort'    => [
                '_id' => -1
            ],
            'typeMap' => DocumentSelector::TYPE_MAP
        ])->andReturn([
            'name' => 'selected'
        ]);

        $result = $selector->offset(1)->where(['name' => 'something'])->sortBy(['_id' => -1])->findOne(
            ['value' => 'x']
        );

        $this->assertInstanceOf(User::class, $result);
        $this->assertSame('selected', $result->name);
    }

    public function testFindOneInheritance()
    {
        $selector = new DocumentSelector(
            $collection = m::mock(Collection::class),
            User::class,
            $this->initODM()
        );

        $collection->shouldReceive('findOne')->with([
            'name'  => 'something',
            'value' => 'x'
        ], [
            'skip'    => 1,
            'limit'   => 0,
            'sort'    => [
                '_id' => -1
            ],
            'typeMap' => DocumentSelector::TYPE_MAP
        ])->andReturn([
            'name'   => 'selected',
            'admins' => 'everything'
        ]);

        $result = $selector->offset(1)->where(['name' => 'something'])->sortBy(['_id' => -1])->findOne(
            ['value' => 'x']
        );

        $this->assertInstanceOf(Admin::class, $result);
        $this->assertSame('selected', $result->name);
        $this->assertSame('everything', $result->admins);
    }

    public function testCollectionFromODM()
    {
        $manager = m::mock(MongoManager::class);
        $odm = $this->initODM($manager);

        $database = m::mock(MongoDatabase::class);
        $manager->shouldReceive('database')->with(null)->andReturn($database);
        $database->shouldReceive('selectCollection')->with('users')->andReturn(m::mock(Collection::class));

        $selector = $odm->selector(User::class);

        $this->assertInstanceOf(DocumentSelector::class, $selector);
        $this->assertSame(User::class, $selector->getClass());
    }

    public function testCollectionFromODMClassInheritance()
    {
        $manager = m::mock(MongoManager::class);
        $odm = $this->initODM($manager);

        $database = m::mock(MongoDatabase::class);
        $manager->shouldReceive('database')->with(null)->andReturn($database);
        $database->shouldReceive('selectCollection')->with('users')->andReturn(m::mock(Collection::class));

        $selector = $odm->selector(Admin::class);

        $this->assertInstanceOf(DocumentSelector::class, $selector);
        $this->assertSame(User::class, $selector->getClass());
    }

    public function testCollectionFromODMCustomCollection()
    {
        $manager = m::mock(MongoManager::class);
        $odm = $this->initODM($manager);

        $database = m::mock(MongoDatabase::class);
        $manager->shouldReceive('database')->with(null)->andReturn($database);
        $database->shouldReceive('selectCollection')->with('moderators')->andReturn(m::mock(Collection::class));

        $selector = $odm->selector(Moderator::class);

        $this->assertInstanceOf(DocumentSelector::class, $selector);
        $this->assertSame(Moderator::class, $selector->getClass());
    }

    public function testCollectionFromODMCustomDatabase()
    {
        $manager = m::mock(MongoManager::class);
        $odm = $this->initODM($manager);

        $database = m::mock(MongoDatabase::class);
        $manager->shouldReceive('database')->with('external')->andReturn($database);
        $database->shouldReceive('selectCollection')->with('externalDBs')->andReturn(m::mock(Collection::class));

        $selector = $odm->selector(ExternalDB::class);

        $this->assertInstanceOf(DocumentSelector::class, $selector);
        $this->assertSame(ExternalDB::class, $selector->getClass());
    }

    /**
     * ODM with predefined classes.
     *
     * @param MongoManager $manager
     *
     * @return \Spiral\ODM\ODM
     */
    protected function initODM(MongoManager $manager = null)
    {
        $builder = $this->makeBuilder();
        $mutators = $this->mutatorsConfig();
        $odm = $this->makeODM($manager);

        $builder->addSchema(new DS(new RE(User::class), $mutators));
        $builder->addSchema(new DS(new RE(Admin::class), $mutators));
        $builder->addSchema(new DS(new RE(SuperAdministrator::class), $mutators));

        $builder->addSchema(new DS(new RE(Moderator::class), $mutators));
        $builder->addSchema(new DS(new RE(SuperModerator::class), $mutators));

        $builder->addSchema(new DS(new RE(ExternalDB::class), $mutators));

        $odm->buildSchema($builder);

        return $odm;
    }
}