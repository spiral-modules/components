<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\Storage\GridFSServer;

use MongoDB\Database;
use MongoDB\Driver\Manager;
use Spiral\Storage\BucketInterface;
use Spiral\Storage\Entities\StorageBucket;
use Spiral\Storage\ServerInterface;
use Spiral\Storage\Servers\GridFSServer;

trait ServerTrait
{
    protected $bucket;

    public function setUp()
    {
        if (empty(env('MONGO_DATABASE'))) {
            $this->skipped = true;
            $this->markTestSkipped('Mongo credentials are not set');
        }
    }

    protected function getBucket(): BucketInterface
    {
        if (!empty($this->bucket)) {
            return $this->bucket;
        }

        $bucket = new StorageBucket(
            'mongo',
            'mongo:',
            ['bucket' => 'grid-fs'],
            $this->getServer()
        );

        $bucket->setLogger($this->makeLogger());

        return $this->bucket = $bucket;
    }

    protected function getServer(): ServerInterface
    {
        return new GridFSServer(
            new Database(new Manager(env('MONGO_CONNECTION')), env('MONGO_DATABASE'))
        );
    }
}