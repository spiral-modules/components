<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\Storage\Servers\LocalServer;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use Spiral\Storage\BucketInterface;
use Spiral\Storage\Entities\StorageBucket;
use Spiral\Storage\ServerInterface;
use Spiral\Storage\Servers\LocalServer;

trait ServerTrait
{
    protected function getBucket(): BucketInterface
    {
        $bucket = new StorageBucket('files', 'file:', ['directory' => '/'], $this->getServer());
        $bucket->setLogger($this->makeLogger());

        return $bucket;
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
        return new LocalServer(['home' => __DIR__ . '/fixtures/']);
    }
}