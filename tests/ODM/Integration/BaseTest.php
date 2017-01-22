<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */
namespace Spiral\Tests\ODM\Integration;

use Mockery as m;
use MongoDB\Driver\Manager;
use Spiral\ODM\Document;
use Spiral\ODM\Entities\MongoDatabase;
use Spiral\ODM\MongoManager;
use Spiral\ODM\ODM;
use Spiral\ODM\Schemas\SchemaBuilder;
use Spiral\Tests\ODM\Fixtures\Admin;
use Spiral\Tests\ODM\Fixtures\DataPiece;
use Spiral\Tests\ODM\Fixtures\User;
use Spiral\Tests\ODM\Traits\ODMTrait;

abstract class BaseTest extends \PHPUnit_Framework_TestCase
{
    use ODMTrait;

    const MODELS = [User::class, Admin::class, DataPiece::class];

    private $skipped;

    /**
     * @var MongoDatabase
     */
    protected $database;

    /**
     * @var MongoDatabase
     */
    static private $staticDatabase;

    /**
     * @var ODM
     */
    protected $odm;

    public function setUp()
    {
        if (empty(env('MONGO_DATABASE'))) {
            $this->skipped = true;
            $this->markTestSkipped('Mongo credentials are not set');
        }

        $this->odm = $this->realODM(static::MODELS);
        $this->database = self::$staticDatabase;
    }

    public function tearDown()
    {
        if (!$this->skipped) {
            /**
             * ATTENTION, DATABASE WILL BE CLEAN AFTER TESTS!
             */
            foreach (self::$staticDatabase->listCollections() as $collection) {
                $collection = self::$staticDatabase->selectCollection($collection->getName());

                if (strpos($collection->getCollectionName(), 'system.') === 0) {
                    continue;
                }

                //Do not even think to test it on real server with real config!
                $collection->drop();
            }
        }
    }

    protected function realODM(array $models)
    {
        $manager = m::mock(MongoManager::class);
        $manager->shouldReceive('database')->with(null)->andReturn(
            self::$staticDatabase ?? self::$staticDatabase = new MongoDatabase(
                new Manager(env('MONGO_CONNECTION')),
                env('MONGO_DATABASE')
            )
        );

        $odm = new ODM($manager);
        $builder = new SchemaBuilder($manager);

        foreach ($models as $model) {
            $builder->addSchema($this->makeSchema($model));
        }

        $odm->buildSchema($builder);

        return $odm;
    }

    protected function fromDB(Document $document): Document
    {
        return $this->odm->source(get_class($document))->findByPK($document->primaryKey());
    }
}