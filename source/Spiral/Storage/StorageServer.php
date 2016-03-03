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
use Spiral\Storage\Exceptions\ServerException;

/**
 * AbstractServer implementation with different naming.
 */
abstract class StorageServer extends Component implements ServerInterface
{
    /**
     * Default mimetype to be used when nothing else can be applied.
     */
    const DEFAULT_MIMETYPE = 'application/octet-stream';

    /**
     * @var array
     */
    protected $options = [];

    /**
     * @var FilesInterface
     */
    protected $files = null;

    /**
     * @param FilesInterface $files   Required for local filesystem operations.
     * @param array          $options Server specific options.
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
        if (empty($stream = $this->allocateStream($bucket, $name))) {
            throw new ServerException("Unable to allocate local filename for '{$name}'.");
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
        if ($this->copy($bucket, $destination, $name)) {
            $this->delete($bucket, $name);

            return true;
        }

        throw new ServerException("Unable to copy '{$name}' to new bucket.");
    }

    /**
     * Cast local filename to be used in file based methods and etc.
     *
     * @param string|StreamInterface $source
     * @return string
     */
    protected function castFilename($source)
    {
        if (empty($source)) {
            return StreamWrapper::getUri(\GuzzleHttp\Psr7\stream_for(''));
        }

        if (is_string($source)) {
            if ($this->files->exists($source)) {
                return $source;
            } else {
                return StreamWrapper::getUri(\GuzzleHttp\Psr7\stream_for($source));
            }
        }

        if ($source instanceof UploadedFileInterface || $source instanceof StreamableInterface) {
            $source = $source->getStream();
        }

        if ($source instanceof StreamInterface) {
            return StreamWrapper::getUri($source);
        }

        throw new ServerException("Unable to get filename for non Stream instance.");
    }

    /**
     * Cast stream associated with origin data.
     *
     * @param string|StreamInterface $source
     * @return StreamInterface
     */
    protected function castStream($source)
    {
        if ($source instanceof UploadedFileInterface || $source instanceof StreamableInterface) {
            $source = $source->getStream();
        }

        if ($source instanceof StreamInterface) {
            return $source;
        }

        if (empty($source)) {
            //This code is going to use additional abstraction layer to connect storage and guzzle
            return \GuzzleHttp\Psr7\stream_for('');
        }

        if (is_string($source) && $this->files->exists($source)) {
            $source = fopen($source, 'rb');
        }

        return \GuzzleHttp\Psr7\stream_for($source);
    }
} 