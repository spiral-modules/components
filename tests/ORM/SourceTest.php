<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ORM;

use Mockery as m;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Spiral\ORM\Entities\RecordSelector;
use Spiral\Pagination\Paginator;
use Spiral\Pagination\PaginatorInterface;
use Spiral\Pagination\PaginatorsInterface;
use Spiral\Tests\ORM\Fixtures\User;
use Spiral\Tests\ORM\Fixtures\UserSource;

abstract class SourceTest extends BaseTest
{
    public function testInstance()
    {
        $source = $this->orm->source(User::class);

        $this->assertSame(User::class, $source->getClass());
        $this->assertInstanceOf(UserSource::class, $this->orm->source(User::class));
        $this->assertSame($this->orm, $source->getORM());

        $this->assertInstanceOf(RecordSelector::class, $source->getIterator());
        $this->assertInstanceOf(RecordSelector::class, $source->find());
    }

    public function testSelectorIsolated()
    {
        $selector = $this->orm->selector(User::class, true);
        $this->assertNotSame($selector->getORM(), $this->orm);

        $this->assertSame(User::class, $selector->getClass());
    }

    public function testSelectorNotIsolated()
    {
        $selector = $this->orm->selector(User::class, false);
        $this->assertSame($selector->getORM(), $this->orm);

        $this->assertSame(User::class, $selector->getClass());

        $this->assertNull($selector->findOne());
    }

    public function testSelectorAndMap()
    {
        $user = new User();
        $user->name = 'Anton';
        $user->save();

        $selector = $this->orm->selector(User::class, true);

        $this->assertSimilar($user, $selector->findOne());
        $this->assertNotSame($user, $selector->findOne());
    }

    public function testSelectorAndMapButRemembered()
    {
        $user = new User();
        $user->name = 'Anton';
        $user->save();

        $this->orm->getMap()->remember($user);
        $selector = $this->orm->selector(User::class, true);

        $this->assertSimilar($user, $selector->findOne());
        $this->assertSame($user, $selector->findOne());
    }

    public function testInstanceAutoInit()
    {
        $source = $this->container->get(UserSource::class);

        $this->assertInstanceOf(UserSource::class, $this->orm->source(User::class));
        $this->assertSame($this->orm, $source->getORM());

        $this->assertInstanceOf(RecordSelector::class, $source->getIterator());
        $this->assertInstanceOf(RecordSelector::class, $source->find());
    }

    public function testInstanceScoped()
    {
        $source = new UserSource();

        $this->assertInstanceOf(UserSource::class, $this->orm->source(User::class));
        $this->assertSame($this->orm, $source->getORM());

        $this->assertInstanceOf(RecordSelector::class, $source->getIterator());
        $this->assertInstanceOf(RecordSelector::class, $source->find());
    }

    public function testCount()
    {
        $user = new User();
        $user->name = 'Anton';
        $user->save();

        $user = new User();
        $user->name = 'John';
        $user->save();

        $source = new UserSource();

        $this->assertSame(2, $source->count());
        $this->assertSame(1, $source->count(['name' => 'Anton']));
    }

    public function testCustomSelector()
    {
        $user = new User();
        $user->name = 'Anton';
        $user->save();

        $user1 = new User();
        $user1->name = 'John';
        $user1->save();

        $source = new UserSource();

        $this->assertSame('Anton', $source->findByPK($user->primaryKey())->name);
        $this->assertSame(2, $source->count());
        $this->assertSame(1, $source->count(['name' => 'Anton']));

        $source1 = $source->withSelector($source->find(['name' => 'John']));

        $this->assertNotSame($source1, $source);

        $this->assertSame(2, $source->count());
        $this->assertSame(1, $source1->count());
        $this->assertSame(0, $source1->count(['name' => 'Anton']));
        $this->assertSame('John', $source1->findOne()->name);
    }

    public function testCreate()
    {
        $source = new UserSource();
        $user = $source->create([
            'name' => 'Bobby'
        ]);

        $this->assertSame('Bobby', $user->name);
    }

    public function testIterate()
    {
        $user = new User();
        $user->name = 'Anton';
        $user->save();

        $count = 0;
        foreach ($this->orm->source(User::class) as $item) {
            $count++;
            $this->assertSimilar($user, $item);
        }

        $this->assertSame(1, $count);
    }

    public function testMapIterate()
    {
        $user = new User();
        $user->name = 'Anton';
        $user->save();

        $this->orm->getMap()->remember($user);

        $count = 0;
        foreach ($this->orm->source(User::class) as $item) {
            $count++;
            //Same instance
            $this->assertSame($user, $item);
        }

        $this->assertSame(1, $count);
    }

    public function testSum()
    {
        $user = new User();
        $user->name = 'Anton';
        $user->balance = 10;
        $user->save();

        $user = new User();
        $user->name = 'Anton';
        $user->balance = 20;
        $user->save();

        $this->assertSame(30, $this->orm->selector(User::class)->sum('balance'));
    }

    public function testPaginate()
    {
        $user = new User();
        $user->name = 'Anton';
        $user->balance = 10;
        $user->save();

        $user1 = new User();
        $user1->name = 'John';
        $user1->balance = 20;
        $user1->save();


        $selector = $this->orm->selector(User::class);
        $this->assertFalse($selector->hasPaginator());
        $selector->setPaginator((new Paginator(1))->withPage(1));

        $this->assertTrue($selector->hasPaginator());
        $this->assertSame(1, $selector->getPaginator()->getLimit());

        foreach ($selector as $entity) {
            $this->assertSimilar($user, $entity);
        }

        $selector->setPaginator($selector->getPaginator()->withPage(2));

        $this->assertTrue($selector->hasPaginator());
        $this->assertSame(1, $selector->getPaginator()->getLimit());

        foreach ($selector as $entity) {
            $this->assertSimilar($user1, $entity);
        }
    }

    public function testPaginateInScope()
    {
        $user = new User();
        $user->name = 'Anton';
        $user->balance = 10;
        $user->save();

        $user1 = new User();
        $user1->name = 'John';
        $user1->balance = 20;
        $user1->save();

        $this->container->bind(PaginatorsInterface::class, new class implements PaginatorsInterface
        {
            public function createPaginator(string $parameter, int $limit = 25): PaginatorInterface
            {
                $paginator = new Paginator($limit);

                return $paginator;
            }
        });

        $selector = $this->orm->selector(User::class);
        $this->assertFalse($selector->hasPaginator());
        $selector->paginate(1);

        $this->assertTrue($selector->hasPaginator());
        $this->assertSame(1, $selector->getPaginator()->getLimit());

        foreach ($selector as $entity) {
            $this->assertSimilar($user, $entity);
        }

        $this->container->bind(PaginatorsInterface::class, new class implements PaginatorsInterface
        {
            public function createPaginator(string $parameter, int $limit = 25): PaginatorInterface
            {
                $paginator = new Paginator($limit);

                return $paginator;
            }
        });

        $selector->paginate(2);

        $this->assertTrue($selector->hasPaginator());
        $this->assertSame(2, $selector->getPaginator()->getLimit());

        $this->container->removeBinding(PaginatorsInterface::class);
    }

    /**
     * @expectedException \Spiral\ORM\Exceptions\MapException
     */
    public function testStoreInMapNew()
    {
        $user = new User();
        $user->name = 'Anton';

        $this->orm->getMap()->remember($user);
    }

    public function testCache()
    {
        $user = new User();
        $user->name = 'Anton';
        $user->balance = 10;
        $user->save();

        //With cache
        $selector = $this->orm->selector(User::class);
        $data = $selector->fetchData();
        $this->assertCount(1, $data);

        $pool = m::mock(CacheItemPoolInterface::class);
        $item = m::mock(CacheItemInterface::class);
        $pool->shouldReceive('getItem')->with('key')->andReturn($item);
        $item->shouldReceive('isHit')->andReturn(true);

        $item->shouldReceive('get')->andReturn($data);

        $cached = $selector->getIterator(
            'key',
            10,
            $pool
        );


        foreach ($cached as $item) {
            $this->assertSimilar($user, $item);
        }
    }

    public function testCacheInScope()
    {
        $user = new User();
        $user->name = 'Anton';
        $user->balance = 10;
        $user->save();

        //With cache
        $selector = $this->orm->selector(User::class);
        $data = $selector->fetchData();
        $this->assertCount(1, $data);

        $pool = m::mock(CacheItemPoolInterface::class);
        $item = m::mock(CacheItemInterface::class);
        $pool->shouldReceive('getItem')->with('key')->andReturn($item);
        $item->shouldReceive('isHit')->andReturn(true);

        $item->shouldReceive('get')->andReturn($data);

        $this->container->bind(CacheItemPoolInterface::class, $pool);

        $cached = $selector->getIterator(
            'key',
            10
        );

        foreach ($cached as $item) {
            $this->assertSimilar($user, $item);
        }


        $this->container->removeBinding(CacheItemPoolInterface::class);
    }

    public function testCacheSet()
    {
        $user = new User();
        $user->name = 'Anton';
        $user->balance = 10;
        $user->save();

        //With cache
        $selector = $this->orm->selector(User::class);
        $data = $selector->fetchData();
        $this->assertCount(1, $data);

        $pool = m::mock(CacheItemPoolInterface::class);
        $item = m::mock(CacheItemInterface::class);
        $pool->shouldReceive('getItem')->with('key')->andReturn($item);
        $item->shouldReceive('isHit')->andReturn(false);

        $item->shouldReceive('set')->with($data)->andReturnSelf();
        $item->shouldReceive('expiresAfter')->with(10)->andReturnSelf();

        $pool->shouldReceive('save')->with($item);

        $cached = $selector->getIterator(
            'key',
            10,
            $pool
        );

        foreach ($cached as $item) {
            $this->assertSimilar($user, $item);
        }
    }
}