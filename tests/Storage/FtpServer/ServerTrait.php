<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\Storage\FtpServer;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use Spiral\Files\FilesInterface;
use Spiral\Storage\BucketInterface;
use Spiral\Storage\Entities\StorageBucket;
use Spiral\Storage\ServerInterface;
use Spiral\Storage\Servers\FtpServer;

trait ServerTrait
{
    protected $bucket;

    public function setUp()
    {
        if (empty(env('STORAGE_FTP_USERNAME'))) {
            $this->skipped = true;
            $this->markTestSkipped('FTP credentials are not set');
        }
    }

    protected function getBucket(): BucketInterface
    {
        if (!empty($this->bucket)) {
            return $this->bucket;
        }

        $bucket = new StorageBucket(
            'ftp',
            env('STORAGE_FTP_PREFIX'),
            [
                'directory' => env('STORAGE_FTP_DIRECTORY'),
                'mode'      => FilesInterface::READONLY
            ],
            $this->getServer()
        );

        $bucket->setLogger($this->makeLogger());

        return $this->bucket = $bucket;
    }

    protected function getServer(): ServerInterface
    {
        return new FtpServer([
            'host'     => env('STORAGE_FTP_HOST'),
            'login'    => env('STORAGE_FTP_USERNAME'),
            'password' => env('STORAGE_FTP_PASSWORD')
        ]);
    }
}