<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\Storage\Servers\AmazonServer;

use Spiral\Storage\BucketInterface;
use Spiral\Storage\Entities\StorageBucket;
use Spiral\Storage\ServerInterface;
use Spiral\Storage\Servers\AmazonServer;

trait ServerTrait
{
    protected $bucket;

    public function setUp()
    {
        if (empty(env('STORAGE_AMAZON_KEY'))) {
            $this->skipped = true;
            $this->markTestSkipped('Amazon credentials are not set');
        }
    }

    protected function getBucket(): BucketInterface
    {
        if (!empty($this->bucket)) {
            return $this->bucket;
        }

        $bucket = new StorageBucket(
            'amazon',
            env('STORAGE_AMAZON_PREFIX'),
            [
                'bucket' => env('STORAGE_AMAZON_BUCKET'),
                'public' => false
            ],
            $this->getServer()
        );

        $bucket->setLogger($this->makeLogger());

        return $this->bucket = $bucket;
    }

    protected function getServer(): ServerInterface
    {
        return new AmazonServer([
            'accessKey' => env('STORAGE_AMAZON_KEY'),
            'secretKey' => env('STORAGE_AMAZON_SECRET')
        ]);
    }
}