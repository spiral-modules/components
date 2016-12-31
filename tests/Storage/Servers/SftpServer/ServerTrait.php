<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\Storage\Servers\SftpServer;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use Spiral\Files\FilesInterface;
use Spiral\Storage\BucketInterface;
use Spiral\Storage\Entities\StorageBucket;
use Spiral\Storage\ServerInterface;
use Spiral\Storage\Servers\SftpServer;

trait ServerTrait
{
    protected $bucket;

    public function setUp()
    {
        if (empty(env('STORAGE_SFTP_USERNAME'))) {
            $this->skipped = true;
            $this->markTestSkipped('SFTP credentials are not set');
        }
    }

    protected function getBucket(): BucketInterface
    {
        if (!empty($this->bucket)) {
            return $this->bucket;
        }

        $bucket = new StorageBucket(
            'sftp',
            env('STORAGE_SFTP_PREFIX'),
            [
                'directory' => env('STORAGE_SFTP_DIRECTORY'),
                'mode'      => FilesInterface::READONLY
            ],
            $this->getServer()
        );

        $bucket->setLogger($this->makeLogger());

        return $this->bucket = $bucket;
    }

    protected function getServer(): ServerInterface
    {
        return new SftpServer([
            'host'     => env('STORAGE_SFTP_HOST'),
            'username' => env('STORAGE_SFTP_USERNAME'),
            'password' => env('STORAGE_SFTP_PASSWORD'),
            'home'     => env('STORAGE_SFTP_HOME'),
        ]);
    }
}