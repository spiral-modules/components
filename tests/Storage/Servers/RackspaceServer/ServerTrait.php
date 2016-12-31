<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\Storage\Servers\RackspaceServer;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use Spiral\Storage\BucketInterface;
use Spiral\Storage\Entities\StorageBucket;
use Spiral\Storage\ServerInterface;
use Spiral\Storage\Servers\RackspaceServer;

trait ServerTrait
{
    protected $bucket;

    public function setUp()
    {
        if (empty(env('STORAGE_RACKSPACE_USERNAME'))) {
            $this->skipped = true;
            $this->markTestSkipped('Rackspace credentials are not set');
        }
    }

    protected function getBucket(): BucketInterface
    {
        if (!empty($this->bucket)) {
            return $this->bucket;
        }

        $bucket = new StorageBucket(
            'rackspace',
            env('STORAGE_RACKSPACE_PREFIX'),
            [
                'container' => env('STORAGE_RACKSPACE_CONTAINER'),
                'region'    => env('STORAGE_RACKSPACE_REGION')
            ],
            $this->getServer()
        );

        $bucket->setLogger($this->makeLogger());

        return $this->bucket = $bucket;
    }

    protected function getServer(): ServerInterface
    {
        return new RackspaceServer([
            'username' => env('STORAGE_RACKSPACE_USERNAME'),
            'apiKey'   => env('STORAGE_RACKSPACE_API_KEY')
        ]);
    }
}