<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ODM;

use Mockery as m;
use MongoDB\Collection;
use Spiral\ODM\Entities\MongoDatabase;
use Spiral\ODM\MongoManager;
use Spiral\ODM\Schemas\SchemaBuilder;
use Spiral\Tests\ODM\Fixtures\Moderator;
use Spiral\Tests\ODM\Fixtures\User;
use Spiral\Tests\ODM\Traits\ODMTrait;

class IndexesTest extends \PHPUnit_Framework_TestCase
{
    use ODMTrait;

    public function testSimpleIndexCreation()
    {
        $builder = new SchemaBuilder($manager = m::mock(MongoManager::class));

        $builder->addSchema($user = $this->makeSchema(User::class));

        $collection = m::mock(Collection::class);
        $collection->shouldReceive('createIndex')->with(['name'], ['unique' => true]);

        $db = m::mock(MongoDatabase::class);
        $db->shouldReceive('selectCollection')
            ->with('users')
            ->andReturn($collection);

        $manager->shouldReceive('database')->with(null)->andReturn($db);

        $builder->createIndexes();
    }

    public function testInderitedIndexCreation()
    {
        $builder = new SchemaBuilder($manager = m::mock(MongoManager::class));

        $builder->addSchema($this->makeSchema(User::class));
        $builder->addSchema($moderator = $this->makeSchema(Moderator::class));

        $collectionU = m::mock(Collection::class);
        $collectionU->shouldReceive('createIndex')->with(['name'], ['unique' => true]);

        $collectionM = m::mock(Collection::class);
        $collectionM->shouldReceive('createIndex')->with(['name'], ['unique' => true]);
        $collectionM->shouldReceive('createIndex')->with(['moderates'], []);

        $db = m::mock(MongoDatabase::class);
        $db->shouldReceive('selectCollection')
            ->with('users')
            ->andReturn($collectionU);

        $db->shouldReceive('selectCollection')
            ->with('moderators')
            ->andReturn($collectionM);

        $manager->shouldReceive('database')->with(null)->andReturn($db);

        $builder->createIndexes();
    }
}