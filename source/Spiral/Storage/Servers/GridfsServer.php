<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Components\Storage\Servers;

use Spiral\Files\FilesInterface;
use Spiral\ODM\MongoDatabase;
use Spiral\ODM\ODM;
use Spiral\Storage\BucketInterface;
use Spiral\Storage\Exceptions\ServerException;
use Spiral\Storage\StorageServer;

/**
 * Provides abstraction level to work with data located in GridFS storage.
 */
class GridfsServer extends StorageServer
{
    /**
     * @var array
     */
    protected $options = [
        'database' => 'default'
    ];

    /**
     * @var MongoDatabase
     */
    protected $database = null;

    /**
     * @param FilesInterface $files
     * @param ODM            $odm
     * @param array          $options
     */
    public function __construct(FilesInterface $files, ODM $odm, array $options)
    {
        parent::__construct($files, $options);
        $this->database = $odm->db($this->options['database']);
    }

    /**
     * {@inheritdoc}
     *
     * @return bool|\MongoGridFSFile
     */
    public function exists(BucketInterface $bucket, $name)
    {
        return $this->gridFS($bucket)->findOne(['filename' => $name]);
    }

    /**
     * {@inheritdoc}
     */
    public function size(BucketInterface $bucket, $name)
    {
        if (!$file = $this->exists($bucket, $name))
        {
            return false;
        }

        return $file->getSize();
    }

    /**
     * {@inheritdoc}
     */
    public function put(BucketInterface $bucket, $name, $source)
    {
        //We have to remove existed file first, this might not be super optimal operation.
        //Can be re-thinked
        $this->delete($bucket, $name);

        /**
         * For some reason mongo driver i have locally don't want to read wrapped streams,
         * it either dies with "error setting up file" or hangs.
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

        if (!$this->gridFS($bucket)->storeFile($tempFilename, ['filename' => $name]))
        {
            throw new ServerException("Unable to store {$name} in GridFS server.");
        }

        $this->files->delete($tempFilename);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function allocateStream(BucketInterface $bucket, $name)
    {
        if (!$file = $this->exists($bucket, $name))
        {
            throw new ServerException(
                "Unable to create stream for '{$name}', object does not exists."
            );
        }

        return \GuzzleHttp\Psr7\stream_for($file->getResource());
    }

    /**
     * {@inheritdoc}
     */
    public function delete(BucketInterface $bucket, $name)
    {
        $this->gridFS($bucket)->remove(['filename' => $name]);
    }

    /**
     * {@inheritdoc}
     */
    public function rename(BucketInterface $bucket, $oldname, $newname)
    {
        $this->delete($bucket, $newname);

        return $this->gridFS($bucket)->update(
            ['filename' => $oldname],
            ['$set' => ['filename' => $newname]]
        );
    }

    /**
     * Get valid gridfs collection associated with container.
     *
     * @param BucketInterface $bucket Bucket instance.
     * @return \MongoGridFS
     */
    protected function gridFS(BucketInterface $bucket)
    {
        $gridFs = $this->database->getGridFS($bucket->getOption('collection'));
        $gridFs->ensureIndex(['filename' => 1]);

        return $gridFs;
    }
}