<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ODM;

use Mockery as m;
use Spiral\Models\Reflections\ReflectionEntity as RE;
use Spiral\ODM\Entities\DocumentInstantiator;
use Spiral\ODM\ODMInterface;
use Spiral\ODM\Schemas\Definitions\IndexDefinition;
use Spiral\ODM\Schemas\DocumentSchema as DS;
use Spiral\Tests\ODM\Fixtures\Admin;
use Spiral\Tests\ODM\Fixtures\DataPiece;
use Spiral\Tests\ODM\Fixtures\ExternalDB;
use Spiral\Tests\ODM\Fixtures\Moderator;
use Spiral\Tests\ODM\Fixtures\SuperAdministrator;
use Spiral\Tests\ODM\Fixtures\SuperModerator;
use Spiral\Tests\ODM\Fixtures\User;
use Spiral\Tests\ODM\Traits\ODMTrait;

class SchemasTest extends \PHPUnit_Framework_TestCase
{
    use ODMTrait;

    public function testGetSchema()
    {
        $builder = $this->makeBuilder();
        $mutators = $this->mutatorsConfig();

        $builder->addSchema(new DS(new RE(User::class), $mutators));

        $this->assertCount(1, $builder->getSchemas());

        //Checking schemas
        $schema = $builder->getSchema(User::class);
        $this->assertInstanceOf(DS::class, $schema);
    }

    /**
     * @expectedException \Spiral\ODM\Exceptions\SchemaException
     * @expectedExceptionMessage Unable to find schema for class 'Spiral\Tests\ODM\Fixtures\Admin'
     */
    public function testGetSchemaException()
    {
        $builder = $this->makeBuilder();
        $mutators = $this->mutatorsConfig();

        $builder->addSchema(new DS(new RE(User::class), $mutators));

        $this->assertCount(1, $builder->getSchemas());

        //Checking schemas
        $schema = $builder->getSchema(Admin::class);
    }

    public function testSchemaValues()
    {
        $builder = $this->makeBuilder();
        $mutators = $this->mutatorsConfig();

        $builder->addSchema(new DS(new RE(User::class), $mutators));
        $user = $builder->getSchema(User::class);

        $this->assertSame(DocumentInstantiator::class, $user->getInstantiator());
        $this->assertSame(User::class, $user->getClass());

        $this->assertSame('users', $user->getCollection());
        $this->assertSame(null, $user->getDatabase());

        $this->assertSame(false, $user->isEmbedded());
        $this->assertEquals([
            new IndexDefinition(['name'], ['unique' => true])
        ], $user->getIndexes());
    }

    public function testSchemaValuesExternal()
    {
        $builder = $this->makeBuilder();
        $mutators = $this->mutatorsConfig();

        $builder->addSchema(new DS(new RE(ExternalDB::class), $mutators));
        $ext = $builder->getSchema(ExternalDB::class);

        $this->assertSame(DocumentInstantiator::class, $ext->getInstantiator());
        $this->assertSame(ExternalDB::class, $ext->getClass());

        //Auto collection name
        $this->assertSame('externalDBs', $ext->getCollection());
        $this->assertSame('external', $ext->getDatabase());

        $this->assertSame(false, $ext->isEmbedded());
        $this->assertEquals([], $ext->getIndexes());
    }

    public function testCollectionInheritance()
    {
        $builder = $this->makeBuilder();
        $mutators = $this->mutatorsConfig();

        $builder->addSchema(new DS(new RE(User::class), $mutators));
        $builder->addSchema(new DS(new RE(Admin::class), $mutators));
        $builder->addSchema(new DS(new RE(SuperAdministrator::class), $mutators));

        $user = $builder->getSchema(User::class);
        $admin = $builder->getSchema(Admin::class);

        $this->assertSame(DocumentInstantiator::class, $admin->getInstantiator());
        $this->assertSame(Admin::class, $admin->getClass());

        //Auto collection name
        $this->assertSame($user->getCollection(), $admin->getCollection());
        $this->assertSame($user->getDatabase(), $admin->getDatabase());

        $this->assertSame(false, $admin->isEmbedded());
        $this->assertEquals([
            new IndexDefinition(['name'], ['unique' => true]),
            new IndexDefinition(['admins'])
        ], $admin->getIndexes());

        //2nd level
        $superAdmin = $builder->getSchema(SuperAdministrator::class);

        $this->assertSame(DocumentInstantiator::class, $superAdmin->getInstantiator());
        $this->assertSame(SuperAdministrator::class, $superAdmin->getClass());

        //Auto collection name
        $this->assertSame($admin->getCollection(), $superAdmin->getCollection());
        $this->assertSame($admin->getDatabase(), $superAdmin->getDatabase());

        $this->assertSame(false, $superAdmin->isEmbedded());
        $this->assertEquals([
            new IndexDefinition(['name'], ['unique' => true]),
            new IndexDefinition(['admins'])
        ], $superAdmin->getIndexes());
    }

    public function testCollectionInheritanceDifferentOrder()
    {
        $builder = $this->makeBuilder();
        $mutators = $this->mutatorsConfig();

        $builder->addSchema(new DS(new RE(SuperAdministrator::class), $mutators));
        $builder->addSchema(new DS(new RE(User::class), $mutators));
        $builder->addSchema(new DS(new RE(Admin::class), $mutators));

        $user = $builder->getSchema(User::class);
        $admin = $builder->getSchema(Admin::class);

        $this->assertSame(DocumentInstantiator::class, $admin->getInstantiator());
        $this->assertSame(Admin::class, $admin->getClass());

        //Auto collection name
        $this->assertSame($user->getCollection(), $admin->getCollection());
        $this->assertSame($user->getDatabase(), $admin->getDatabase());

        $this->assertSame(false, $admin->isEmbedded());
        $this->assertEquals([
            new IndexDefinition(['name'], ['unique' => true]),
            new IndexDefinition(['admins'])
        ], $admin->getIndexes());

        //2nd level
        $superAdmin = $builder->getSchema(SuperAdministrator::class);

        $this->assertSame(DocumentInstantiator::class, $superAdmin->getInstantiator());
        $this->assertSame(SuperAdministrator::class, $superAdmin->getClass());

        //Auto collection name
        $this->assertSame($admin->getCollection(), $superAdmin->getCollection());
        $this->assertSame($admin->getDatabase(), $superAdmin->getDatabase());

        $this->assertSame(false, $superAdmin->isEmbedded());
        $this->assertEquals([
            new IndexDefinition(['name'], ['unique' => true]),
            new IndexDefinition(['admins'])
        ], $superAdmin->getIndexes());
    }

    public function testCollectionRedefinition()
    {
        $builder = $this->makeBuilder();
        $mutators = $this->mutatorsConfig();

        $builder->addSchema(new DS(new RE(User::class), $mutators));
        $builder->addSchema(new DS(new RE(Moderator::class), $mutators));
        $builder->addSchema(new DS(new RE(SuperModerator::class), $mutators));

        $user = $builder->getSchema(User::class);
        $moderator = $builder->getSchema(Moderator::class);

        $this->assertSame(DocumentInstantiator::class, $moderator->getInstantiator());
        $this->assertSame(Moderator::class, $moderator->getClass());

        //Auto collection name
        $this->assertSame('users', $user->getCollection());
        $this->assertSame('moderators', $moderator->getCollection());
        $this->assertSame(null, $moderator->getDatabase());

        $this->assertSame(false, $moderator->isEmbedded());
        $this->assertEquals([
            new IndexDefinition(['name'], ['unique' => true]),
            new IndexDefinition(['moderates'])
        ], $moderator->getIndexes());

        //2nd level
        $superModerator = $builder->getSchema(SuperModerator::class);

        $this->assertSame(DocumentInstantiator::class, $superModerator->getInstantiator());
        $this->assertSame(SuperModerator::class, $superModerator->getClass());

        //Auto collection name
        $this->assertSame($moderator->getCollection(), $superModerator->getCollection());
        $this->assertSame($moderator->getDatabase(), $superModerator->getDatabase());

        $this->assertSame(false, $superModerator->isEmbedded());
        $this->assertEquals([
            new IndexDefinition(['name'], ['unique' => true]),
            new IndexDefinition(['moderates'])
        ], $superModerator->getIndexes());
    }

    public function testCollectionRedefinitionDifferentOrder()
    {
        $builder = $this->makeBuilder();
        $mutators = $this->mutatorsConfig();

        $builder->addSchema(new DS(new RE(SuperModerator::class), $mutators));
        $builder->addSchema(new DS(new RE(Moderator::class), $mutators));
        $builder->addSchema(new DS(new RE(User::class), $mutators));

        $user = $builder->getSchema(User::class);
        $moderator = $builder->getSchema(Moderator::class);

        $this->assertSame(DocumentInstantiator::class, $moderator->getInstantiator());
        $this->assertSame(Moderator::class, $moderator->getClass());

        //Auto collection name
        $this->assertSame('users', $user->getCollection());
        $this->assertSame('moderators', $moderator->getCollection());
        $this->assertSame(null, $moderator->getDatabase());

        $this->assertSame(false, $moderator->isEmbedded());
        $this->assertEquals([
            new IndexDefinition(['name'], ['unique' => true]),
            new IndexDefinition(['moderates'])
        ], $moderator->getIndexes());

        //2nd level
        $superModerator = $builder->getSchema(SuperModerator::class);

        $this->assertSame(DocumentInstantiator::class, $superModerator->getInstantiator());
        $this->assertSame(SuperModerator::class, $superModerator->getClass());

        //Auto collection name
        $this->assertSame($moderator->getCollection(), $superModerator->getCollection());
        $this->assertSame($moderator->getDatabase(), $superModerator->getDatabase());

        $this->assertSame(false, $superModerator->isEmbedded());
        $this->assertEquals([
            new IndexDefinition(['name'], ['unique' => true]),
            new IndexDefinition(['moderates'])
        ], $superModerator->getIndexes());
    }

    public function testEmbedded()
    {
        $builder = $this->makeBuilder();
        $mutators = $this->mutatorsConfig();

        $builder->addSchema(new DS(new RE(DataPiece::class), $mutators));

        $piece = $builder->getSchema(DataPiece::class);

        $this->assertSame(true, $piece->isEmbedded());
    }

    /**
     * @expectedException \Spiral\ODM\Exceptions\SchemaException
     * @expectedExceptionMessage Unable to get database name for embedded model
     *                           Spiral\Tests\ODM\Fixtures\DataPiece
     */
    public function testEmbeddedExceptionGetDatabase()
    {
        $builder = $this->makeBuilder();
        $mutators = $this->mutatorsConfig();

        $builder->addSchema(new DS(new RE(DataPiece::class), $mutators));

        $piece = $builder->getSchema(DataPiece::class);
        $this->assertSame(true, $piece->isEmbedded());

        //Exception
        $piece->getDatabase();
    }

    /**
     * @expectedException \Spiral\ODM\Exceptions\SchemaException
     * @expectedExceptionMessage Unable to get collection name for embedded model
     *                           Spiral\Tests\ODM\Fixtures\DataPiece
     */
    public function testEmbeddedExceptionGetCollection()
    {
        $builder = $this->makeBuilder();
        $mutators = $this->mutatorsConfig();

        $builder->addSchema(new DS(new RE(DataPiece::class), $mutators));

        $piece = $builder->getSchema(DataPiece::class);
        $this->assertSame(true, $piece->isEmbedded());

        //Exception
        $piece->getCollection();
    }

    /**
     * @expectedException \Spiral\ODM\Exceptions\SchemaException
     * @expectedExceptionMessage Unable to get indexes for embedded model
     *                           Spiral\Tests\ODM\Fixtures\DataPiece
     */
    public function testEmbeddedExceptionGetIndexes()
    {
        $builder = $this->makeBuilder();
        $mutators = $this->mutatorsConfig();

        $builder->addSchema(new DS(new RE(DataPiece::class), $mutators));

        $piece = $builder->getSchema(DataPiece::class);
        $this->assertSame(true, $piece->isEmbedded());

        //Exception
        $piece->getIndexes();
    }

    public function testSchemaPackSimple()
    {
        $builder = $this->makeBuilder();
        $mutators = $this->mutatorsConfig();

        $builder->addSchema(new DS(new RE(User::class), $mutators));
        $builder->addSchema(new DS(new RE(Admin::class), $mutators));
        $builder->addSchema(new DS(new RE(Moderator::class), $mutators));

        $this->assertCount(3, $builder->getSchemas());

        //Packing
        $packed = $builder->packSchema();

        $this->assertArrayHasKey(User::class, $packed);
        $this->assertArrayHasKey(Admin::class, $packed);
        $this->assertArrayHasKey(Moderator::class, $packed);

        //Checking all keys
        foreach ($packed as $item) {
            $this->assertInternalType('array', $item);

            $this->assertArrayHasKey(ODMInterface::D_INSTANTIATOR, $item);
            $this->assertArrayHasKey(ODMInterface::D_SCHEMA, $item);
            $this->assertArrayHasKey(ODMInterface::D_DATABASE, $item);
            $this->assertArrayHasKey(ODMInterface::D_DATABASE, $item);
        }
    }


    public function testSchemaPackSimpleEmbedded()
    {
        $builder = $this->makeBuilder();
        $mutators = $this->mutatorsConfig();

        $builder->addSchema(new DS(new RE(DataPiece::class), $mutators));

        $this->assertCount(1, $builder->getSchemas());

        //Packing
        $packed = $builder->packSchema();

        $this->assertArrayHasKey(DataPiece::class, $packed);

        //Checking all keys
        foreach ($packed as $item) {
            $this->assertInternalType('array', $item);

            $this->assertArrayHasKey(ODMInterface::D_INSTANTIATOR, $item);
            $this->assertArrayHasKey(ODMInterface::D_SCHEMA, $item);
            $this->assertArrayNotHasKey(ODMInterface::D_DATABASE, $item);
            $this->assertArrayNotHasKey(ODMInterface::D_DATABASE, $item);
        }
    }
}