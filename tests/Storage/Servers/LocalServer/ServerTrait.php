<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\Storage\Servers\LocalServer;

use Spiral\Storage\BucketInterface;
use Spiral\Storage\Entities\StorageBucket;
use Spiral\Storage\ServerInterface;
use Spiral\Storage\Servers\LocalServer;

trait ServerTrait
{
    protected $bucket;

    protected function getBucket(): BucketInterface
    {
        if (!empty($this->bucket)) {
            return $this->bucket;
        }

        $bucket = new StorageBucket('files', 'file:', ['directory' => '/'], $this->getServer());
        $bucket->setLogger($this->makeLogger());

        return $this->bucket = $bucket;
    }

    protected function getServer(): ServerInterface
    {
        return new LocalServer(['home' => __DIR__ . '/fixtures/']);
    }
}