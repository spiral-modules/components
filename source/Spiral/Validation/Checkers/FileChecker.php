<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Validation\Checkers;

use Interop\Container\ContainerInterface;
use Psr\Http\Message\UploadedFileInterface;
use Spiral\Core\Container\SingletonInterface;
use Spiral\Files\FilesInterface;
use Spiral\Files\Streams\StreamableInterface;
use Spiral\Files\Streams\StreamWrapper;
use Spiral\Validation\Prototypes\AbstractChecker;

class FileChecker extends AbstractChecker implements SingletonInterface
{
    /**
     * {@inheritdoc}
     */
    protected $messages = [
        'exists'    => '[[File does not exists.]]',
        'uploaded'  => '[[File not received, please try again.]]',
        'size'      => '[[File exceeds the maximum file size of {1}KB.]]',
        'extension' => '[[File has an invalid file format.]]',
    ];

    /**
     * @var FilesInterface
     */
    protected $files = null;

    /**
     * @param FilesInterface     $files
     * @param ContainerInterface $container
     */
    public function __construct(FilesInterface $files, ContainerInterface $container = null)
    {
        $this->files = $files;

        parent::__construct($container);
    }

    /**
     * Check if file exist.
     *
     * @param mixed $filename
     *
     * @return bool
     */
    public function exists($filename): bool
    {
        return (bool)$this->filename($filename, false);
    }

    /**
     * Will check if local file exists or just uploaded.
     *
     * @param mixed $file Local file or uploaded file array.
     *
     * @return bool
     */
    public function uploaded($file): bool
    {
        return (bool)$this->filename($file, true);
    }

    /**
     * Check if file size less that specified value in KB.
     *
     * @param mixed $filename Local file or uploaded file array.
     * @param int   $size     Size in KBytes.
     *
     * @return bool
     */
    public function size($filename, int $size): bool
    {
        $filename = $this->filename($filename, false);
        if (empty($filename) || !is_string($filename)) {
            return false;
        }

        return $this->files->size($filename) < $size * 1024;
    }

    /**
     * Check if file extension in whitelist. Client name of uploaded file will be used!
     *
     * @param mixed        $filename
     * @param array|string $extensions
     *
     * @return bool
     */
    public function extension($filename, $extensions): bool
    {
        if (!is_array($extensions)) {
            $extensions = array_slice(func_get_args(), 1);
        }

        if ($filename instanceof UploadedFileInterface) {
            return in_array(
                $this->files->extension($filename->getClientFilename()),
                $extensions
            );
        }

        return in_array($this->files->extension($filename), $extensions);
    }

    /**
     * Internal method to fetch filename using multiple input formats.
     *
     * @param mixed|UploadedFileInterface $filename
     * @param bool                        $onlyUploaded Check if file uploaded.
     *
     * @return string|bool
     */
    protected function filename($filename, bool $onlyUploaded = true)
    {
        if (empty($filename) || ($onlyUploaded && !$this->isUploaded($filename))) {
            return false;
        }

        if (
            $filename instanceof UploadedFileInterface
            || $filename instanceof StreamableInterface
        ) {
            return StreamWrapper::getUri($filename->getStream());
        }

        if (is_array($filename)) {
            $filename = $filename['tmp_name'];
        }

        return $this->files->exists($filename) ? $filename : false;
    }

    /**
     * Check if file being uploaded.
     *
     * @param mixed|UploadedFileInterface $filename Filename or file array.
     *
     * @return bool
     */
    private function isUploaded($filename): bool
    {
        if (is_string($filename)) {
            //We can use native method
            return is_uploaded_file($filename);
        }

        if (is_array($filename)) {
            return isset($filename['tmp_name']) && is_uploaded_file($filename['tmp_name']);
        }

        if ($filename instanceof UploadedFileInterface) {
            return empty($filename->getError());
        }

        return false;
    }
}
