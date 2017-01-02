<?php
/**
 * Spiral Framework, Core Components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ODM;

use Mockery as m;
use MongoDB\BSON\ObjectID;
use MongoDB\Collection;
use MongoDB\InsertOneResult;
use Spiral\ODM\Entities\MongoDatabase;
use Spiral\ODM\MongoManager;
use Spiral\Tests\ODM\Fixtures\User;
use Spiral\Tests\ODM\Traits\ODMTrait;

class ActiveRecordTest extends \PHPUnit_Framework_TestCase
{
    use ODMTrait;

    public function testSaving()
    {
        $manager = m::mock(MongoManager::class);

        $builder = $this->makeBuilder();
        $odm = $this->makeODM($manager);

        $builder->addSchema($this->makeSchema(User::class));
        $odm->buildSchema($builder);

        $user = $odm->make(User::class, []);
        $this->assertInstanceOf(User::class, $user);

        $db = m::mock(MongoDatabase::class);
        $cl = m::mock(Collection::class);

        $result = m::mock(InsertOneResult::class);

        $manager->shouldReceive('database')->with(null)->andReturn($db);
        $db->shouldReceive('selectCollection')->with('users')->andReturn($cl);

        $cl->shouldReceive('insertOne')->with([
            'name'  => 'name',
            'piece' => null
        ])->andReturn($result);

        $result->shouldReceive('getInsertedId')->andReturn(new ObjectID('507f191e810c19729de860ea'));

        $user->name = 'name';

        $this->assertFalse($user->isLoaded());
        $this->assertSame(User::CREATED, $user->save());
        $this->assertTrue($user->isLoaded());

        $this->assertSame('507f191e810c19729de860ea', (string)$user->primaryKey());
    }


    public function testUnchanged()
    {
        $manager = m::mock(MongoManager::class);

        $builder = $this->makeBuilder();
        $odm = $this->makeODM($manager);

        $builder->addSchema($this->makeSchema(User::class));
        $odm->buildSchema($builder);

        $user = $odm->make(User::class, [
            '_id'  => new ObjectID('507f191e810c19729de860ea'),
            'name' => 'test name'
        ], false);

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame(User::UNCHANGED, $user->save());
    }


    public function testUpdate()
    {
        $manager = m::mock(MongoManager::class);

        $builder = $this->makeBuilder();
        $odm = $this->makeODM($manager);

        $builder->addSchema($this->makeSchema(User::class));
        $odm->buildSchema($builder);

        $user = $odm->make(User::class, [
            '_id'  => $_id = new ObjectID('507f191e810c19729de860ea'),
            'name' => 'test name'
        ], false);

        $this->assertInstanceOf(User::class, $user);

        $db = m::mock(MongoDatabase::class);
        $cl = m::mock(Collection::class);

        $manager->shouldReceive('database')->with(null)->andReturn($db);
        $db->shouldReceive('selectCollection')->with('users')->andReturn($cl);

        $cl->shouldReceive('updateOne')->with([
            '_id' => $_id
        ], [
            '$set' => [
                'name' => 'new name'
            ]
        ]);

        $user->name = 'new name';
        $this->assertSame(User::UPDATED, $user->save());
    }

    public function testDelete()
    {
        $manager = m::mock(MongoManager::class);

        $builder = $this->makeBuilder();
        $odm = $this->makeODM($manager);

        $builder->addSchema($this->makeSchema(User::class));
        $odm->buildSchema($builder);

        $user = $odm->make(User::class, [
            '_id'  => $_id = new ObjectID('507f191e810c19729de860ea'),
            'name' => 'test name'
        ], false);

        $this->assertInstanceOf(User::class, $user);

        $db = m::mock(MongoDatabase::class);
        $cl = m::mock(Collection::class);

        $manager->shouldReceive('database')->with(null)->andReturn($db);
        $db->shouldReceive('selectCollection')->with('users')->andReturn($cl);

        $cl->shouldReceive('deleteOne')->with([
            '_id' => $_id
        ]);

        $user->delete();
        $this->assertFalse($user->isLoaded());

    }

    public function testDeleteNonCreated()
    {
        $manager = m::mock(MongoManager::class);

        $builder = $this->makeBuilder();
        $odm = $this->makeODM($manager);

        $builder->addSchema($this->makeSchema(User::class));
        $odm->buildSchema($builder);

        $user = $odm->make(User::class, [
            //'_id'  => $_id = new ObjectID('507f191e810c19729de860ea'),
            'name' => 'test name'
        ], false);

        $this->assertInstanceOf(User::class, $user);

        //Nothing should happend
        $user->delete();
    }
}