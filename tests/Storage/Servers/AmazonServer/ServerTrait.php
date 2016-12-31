<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\Storage\Servers\AmazonServer;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
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

    protected function makeLogger()
    {
        if (static::PROFILING) {
            return new class implements LoggerInterface
            {
                use LoggerTrait;

                public function log($level, $message, array $context = [])
                {
                    if ($level == LogLevel::ERROR) {
                        echo " \n! \033[31m" . $message . "\033[0m";
                    } elseif ($level == LogLevel::ALERT) {
                        echo " \n! \033[35m" . $message . "\033[0m";
                    } else {
                        echo " \n> \033[33m" . $message . "\033[0m";
                    }
                }
            };
        }

        return new NullLogger();
    }

    protected function getServer(): ServerInterface
    {
        return new AmazonServer([
            'accessKey' => env('STORAGE_AMAZON_KEY'),
            'secretKey' => env('STORAGE_AMAZON_SECRET')
        ]);
    }
}