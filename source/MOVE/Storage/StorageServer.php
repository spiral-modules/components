<?php
/**
 * Spiral Framework, SpiralScout LLC.
 *
 * @package   spiralFramework
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2011
 */

namespace Spiral\Storage;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Spiral\Core\Component;
use Spiral\Files\FilesInterface;
use Spiral\Files\Streams\StreamableInterface;
use Spiral\Files\Streams\StreamWrapper;

abstract class StorageServer extends Component implements ServerInterface
{
    /**
     * Default mimetype to be used when nothing else can be applied.
     */
    const DEFAULT_MIMETYPE = 'application/octet-stream';

    /**
     * Server configuration, connection options, auth keys and certificates.
     *
     * @var array
     */
    protected $options = [];

    /**
     * File component.
     *
     * @var FilesInterface
     */
    protected $files = null;

    /**
     * Every server represent one virtual storage which can be either local, remote or cloud based.
     * Every server should support basic set of low-level operations (create, move, copy and etc).
     *
     * @param FilesInterface $files   File component.
     * @param array          $options Storage connection options.
     */
    public function __construct(FilesInterface $files, array $options)
    {
        $this->options = $options + $this->options;
        $this->files = $files;
    }

    /**
     * {@inheritdoc}
     */
    public function allocateFilename(BucketInterface $bucket, $name)
    {
        if (empty($stream = $this->allocateStream($bucket, $name)))
        {
            return false;
        }

        //Default implementation will use stream to create temporary filename, such filename
        //can't be used outside php scope
        return StreamWrapper::getUri($stream);
    }

    /**
     * {@inheritdoc}
     */
    public function copy(BucketInterface $bucket, BucketInterface $destination, $name)
    {
        return $this->put($destination, $name, $this->allocateStream($bucket, $name));
    }

    /**
     * {@inheritdoc}
     */
    public function replace(BucketInterface $bucket, BucketInterface $destination, $name)
    {
        if ($this->copy($bucket, $destination, $name))
        {
            $this->delete($bucket, $name);

            return true;
        }

        return false;
    }

    /**
     * Get filename to be used in file based methods and etc. Will create virtual Uri for streams.
     *
     * @param string|StreamInterface $origin
     * @return string
     */
    protected function castFilename($origin)
    {
        if (empty($origin) || is_string($origin))
        {
            if (!$this->files->exists($origin))
            {
                return StreamWrapper::getUri(\GuzzleHttp\Psr7\stream_for(''));
            }

            return $origin;
        }

        if ($origin instanceof UploadedFileInterface || $origin instanceof StreamableInterface)
        {
            $origin = $origin->getStream();
        }

        if ($origin instanceof StreamInterface)
        {
            return StreamWrapper::getUri($origin);
        }

        throw new StorageException("Unable to get filename for non Stream instance.");
    }

    /**
     * Get stream associated with origin data.
     *
     * @param string|StreamInterface $origin
     * @return StreamInterface
     */
    protected function castStream($origin)
    {
        if ($origin instanceof UploadedFileInterface || $origin instanceof StreamableInterface)
        {
            $origin = $origin->getStream();
        }

        if ($origin instanceof StreamInterface)
        {
            return $origin;
        }

        if (empty($origin))
        {
            return \GuzzleHttp\Psr7\stream_for('');
        }

        return \GuzzleHttp\Psr7\stream_for($origin);
    }
} 