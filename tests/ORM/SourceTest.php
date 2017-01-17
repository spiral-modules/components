<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ORM;

use Spiral\ORM\Entities\RecordSelector;
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

        $this->assertSame(1, $source->count());
        $this->assertSame(0, $source->count(['name' => 'Anton']));
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

    /**
     * @expectedException \Spiral\ORM\Exceptions\MapException
     */
    public function testStoreInMapNew()
    {
        $user = new User();
        $user->name = 'Anton';

        $this->orm->getMap()->remember($user);
    }
}