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
use Spiral\Storage\Prototypes\StorageServer;

/**
 * Provides abstraction level to work with data located in GridFS storage.
 *
 * Attention, server depends on ODM!
 */
class GridFsServer extends StorageServer
{
    /**
     * @var Database
     */
    protected $database;

    /**
     * @param FilesInterface $files
     * @param Database       $database
     * @param array          $options
     */
    public function __construct(FilesInterface $files, Database $database, array $options)
    {
        parent::__construct($files, $options);

        $this->database = $database;
    }

    /**
     * {@inheritdoc}
     *
     * @return bool|\MongoGridFSFile
     */
    public function exists(BucketInterface $bucket, string $name): bool
    {
        //todo: check it
        return $this->getGridFs($bucket)->findOne(['filename' => $name]);
    }

    /**
     * {@inheritdoc}
     */
    public function size(BucketInterface $bucket, string $name)
    {
        if (!$file = $this->exists($bucket, $name)) {
            return false;
        }

        return $file->getSize();
    }

    /**
     * {@inheritdoc}
     */
    public function put(BucketInterface $bucket, string $name, $source): bool
    {
        //We have to remove existed file first, this might not be super optimal operation.
        //Can be re-thinked
        $this->delete($bucket, $name);

        /**
         * For some reason mongo driver i have don't want to read wrapped streams, it either dies
         * with "error setting up file" or hangs.
         *
         * I was not able to debug cause of this error at this moment as i don't have Visual Studio
         * at this PC.
         *
         * However, error caused by some code from this file. In a meantime i will write content to
         * local file before sending it to mongo, this is DIRTY, but will work for some time.
         *
         * @link https://github.com/mongodb/mongo-php-driver/blob/master/gridfs/gridfs.c
         */
        $tempFilename = $this->files->tempFilename();
        copy($this->castFilename($source), $tempFilename);

        if (!$this->getGridFs($bucket)->storeFile($tempFilename, ['filename' => $name])) {
            throw new ServerException("Unable to store {$name} in GridFS server");
        }

        $this->files->delete($tempFilename);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function allocateStream(BucketInterface $bucket, string $name): StreamInterface
    {
        if (!$file = $this->exists($bucket, $name)) {
            throw new ServerException(
                "Unable to create stream for '{$name}', object does not exists"
            );
        }

        return \GuzzleHttp\Psr7\stream_for($file->getResource());
    }

    /**
     * {@inheritdoc}
     */
    public function delete(BucketInterface $bucket, string $name)
    {
        $this->getGridFs($bucket)->remove(['filename' => $name]);
    }

    /**
     * {@inheritdoc}
     */
    public function rename(BucketInterface $bucket, string $oldName, string $newName): bool
    {
        $this->delete($bucket, $newName);

        return $this->getGridFs($bucket)->update(
            ['filename' => $oldName],
            ['$set' => ['filename' => $newName]]
        );
    }

    /**
     * Get valid gridfs collection associated with container.
     *
     * @param BucketInterface $bucket Bucket instance.
     *
     * @return Bucket
     */
    protected function getGridFs(BucketInterface $bucket): Bucket
    {
        return $this->database->selectGridFSBucket($bucket->getOption('collection'));
    }
}