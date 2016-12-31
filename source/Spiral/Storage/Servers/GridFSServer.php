<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Storage\Servers;

use MongoDB\Database;
use MongoDB\GridFS\Bucket;
use Psr\Http\Message\StreamInterface;
use Spiral\Files\FilesInterface;
use Spiral\Storage\BucketInterface;
use Spiral\Storage\Exceptions\ServerException;

/**
 * Provides abstraction level to work with data located in GridFS storage.
 */
class GridFSServer extends AbstractServer
{
    /**
     * @var Database
     */
    protected $database;

    /**
     * @param Database            $database
     * @param FilesInterface|null $files
     */
    public function __construct(Database $database, FilesInterface $files = null)
    {
        parent::__construct([], $files);
        $this->database = $database;
    }

    /**
     * {@inheritdoc}
     *
     * @return bool|\MongoGridFSFile
     */
    public function exists(BucketInterface $bucket, string $name): bool
    {
        return $this->gridFS($bucket)->findOne(['filename' => $name]) !== null;
    }

    /**
     * {@inheritdoc}
     */
    public function size(BucketInterface $bucket, string $name)
    {
        if (!$this->exists($bucket, $name)) {
            return null;
        }

        return $this->gridFS($bucket)->findOne(['filename' => $name])->length;
    }

    /**
     * {@inheritdoc}
     */
    public function put(BucketInterface $bucket, string $name, $source): bool
    {
        $result = $this->gridFS($bucket)->uploadFromStream(
            $name,
            fopen($this->castFilename($source), 'rb')
        );

        if (empty($result)) {
            throw new ServerException("Unable to store {$name} in GridFS server");
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function allocateStream(BucketInterface $bucket, string $name): StreamInterface
    {
        $file = $this->gridFS($bucket)->findOne(['filename' => $name]);
        if (empty($file)) {
            throw new ServerException(
                "Unable to create stream for '{$name}', object does not exists"
            );
        }

        return \GuzzleHttp\Psr7\stream_for(
            $this->gridFS($bucket)->openDownloadStream($file->_id)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function delete(BucketInterface $bucket, string $name)
    {
        $file = $this->gridFS($bucket)->findOne(['filename' => $name]);
        if (!empty($file)) {
            $this->gridFS($bucket)->delete($file->_id);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rename(BucketInterface $bucket, string $oldName, string $newName): bool
    {
        $file = $this->gridFS($bucket)->findOne(['filename' => $oldName]);

        if (empty($file)) {
            return false;
        }

        $this->gridFS($bucket)->rename($file->_id, $newName);

        return true;
    }

    /**
     * Get valid gridfs collection associated with container.
     *
     * @param BucketInterface $bucket Bucket instance.
     *
     * @return Bucket
     */
    protected function gridFS(BucketInterface $bucket): Bucket
    {
        return $this->database->selectGridFSBucket(['bucketName' => $bucket->getOption('bucket')]);
    }
}