<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Components\Storage\Servers;

use Psr\Http\Message\StreamInterface;
use Spiral\Files\FilesInterface;
use Spiral\ODM\MongoDatabase;
use Spiral\ODM\ODM;
use Spiral\Storage\BucketInterface;
use Spiral\Storage\StorageServer;

class GridfsServer extends StorageServer
{
    /**
     * Server configuration, connection options, auth keys and certificates.
     *
     * @var array
     */
    protected $options = [
        'database' => 'default'
    ];

    /**
     * Associated mongo database.
     *
     * @var MongoDatabase
     */
    protected $database = null;

    /**
     * Every server represent one virtual storage which can be either local, remote or cloud based.
     * Every server should support basic set of low-level operations (create, move, copy and etc).
     *
     * @param FilesInterface $files   File component.
     * @param array          $options Storage connection options.
     * @param ODM            $odm     ODM manager is required to resolve MongoDatabase.
     */
    public function __construct(FilesInterface $files, array $options, ODM $odm = null)
    {
        parent::__construct($files, $options);
        $odm = $odm ?: ODM::getInstance();

        $this->database = $odm->db($this->options['database']);
    }

    /**
     * Check if given object (name) exists in specified container. Method should never fail if file
     * not exists and will return bool in any condition.
     *
     * @param BucketInterface $bucket Bucket instance associated with specific server.
     * @param string          $name   Storage object name.
     * @return bool|\MongoGridFSFile
     */
    public function exists(BucketInterface $bucket, $name)
    {
        return $this->getGridFS($bucket)->findOne([
            'filename' => $name
        ]);
    }

    /**
     * Retrieve object size in bytes, should return false if object does not exists.
     *
     * @param BucketInterface $bucket Bucket instance.
     * @param string          $name   Storage object name.
     * @return int|bool
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
     * Upload storage object using given filename or stream. Method can return false in case of failed
     * upload or thrown custom exception if needed.
     *
     * @param BucketInterface        $bucket Bucket instance.
     * @param string                 $name   Given storage object name.
     * @param string|StreamInterface $origin Local filename or stream to use for creation.
     * @return bool
     */
    public function put(BucketInterface $bucket, $name, $origin)
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
        copy($this->castFilename($origin), $tempFilename);

        if (!$this->getGridFS($bucket)->storeFile($tempFilename, ['filename' => $name]))
        {
            return false;
        }

        $this->files->delete($tempFilename);

        return true;
    }

    /**
     * Get temporary read-only stream used to represent remote content. This method is very similar
     * to localFilename, however in some cases it may store data content in memory.
     *
     * Method should return false or thrown an exception if stream can not be allocated.
     *
     * @param BucketInterface $bucket Bucket instance.
     * @param string          $name   Storage object name.
     * @return StreamInterface|false
     */
    public function allocateStream(BucketInterface $bucket, $name)
    {
        if (!$file = $this->exists($bucket, $name))
        {
            return false;
        }

        return \GuzzleHttp\Psr7\stream_for($file->getResource());
    }

    /**
     * Delete storage object from specified container. Method should not fail if object does not
     * exists.
     *
     * @param BucketInterface $bucket Bucket instance.
     * @param string          $name   Storage object name.
     */
    public function delete(BucketInterface $bucket, $name)
    {
        $this->getGridFS($bucket)->remove(['filename' => $name]);
    }

    /**
     * Rename storage object without changing it's container. This operation does not require
     * object recreation or download and can be performed on remote server.
     *
     * Method should return false or thrown an exception if object can not be renamed.
     *
     * @param BucketInterface $bucket  Bucket instance.
     * @param string          $oldname Storage object name.
     * @param string          $newname New storage object name.
     * @return bool
     */
    public function rename(BucketInterface $bucket, $oldname, $newname)
    {
        $this->delete($bucket, $newname);

        return $this->getGridFS($bucket)->update(
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
    protected function getGridFS(BucketInterface $bucket)
    {
        $gridFs = $this->database->getGridFS($bucket->getOption('collection'));
        $gridFs->ensureIndex(['filename' => 1]);

        return $gridFs;
    }
}