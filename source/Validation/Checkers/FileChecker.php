<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Validation\Checkers;

use Psr\Http\Message\UploadedFileInterface;
use Spiral\Files\FilesInterface;
use Spiral\Files\Streams\StreamableInterface;
use Spiral\Files\Streams\StreamWrapper;
use Spiral\Validation\Checker;

class FileChecker extends Checker
{
    /**
     * Set of default error messages associated with their check methods organized by method name.
     * Will be returned by the checker to replace the default validator message. Can have placeholders
     * for interpolation.
     *
     * @var array
     */
    protected $messages = [
        "exists"    => "[[There was an error while uploading '{field}' file.]]",
        "size"      => "[[File '{field}' exceeds the maximum file size of {1}KB.]]",
        "extension" => "[[File '{field}' has an invalid file format.]]"
    ];

    /**
     * FileManager component.
     *
     * @var FilesInterface
     */
    protected $files = null;

    /**
     * New instance for file checker. File checker depends on the File component.
     *
     * @param FilesInterface $files
     */
    public function __construct(FilesInterface $files)
    {
        $this->files = $files;
    }

    /**
     * Helper function that retrieves the real filename. Method can accept as real filename or instance
     * of UploadedFileInterface. Use second parameter to pass only uploaded files.
     *
     * @param string|UploadedFileInterface $file         Local filename or uploaded file array.
     * @param bool                         $onlyUploaded Pass only uploaded files.
     * @return string|bool
     */
    protected function getFilename($file, $onlyUploaded = true)
    {
        if ($onlyUploaded && !$this->files->isUploaded($file))
        {
            return false;
        }

        if ($file instanceof UploadedFileInterface || $file instanceof StreamableInterface)
        {
            return StreamWrapper::getUri($file->getStream());
        }

        if (is_array($file))
        {
            $file = $file['tmp_name'];
        }

        return $this->files->exists($file) ? $file : false;
    }

    /**
     * Will check if the local file exists.
     *
     * @param array|string $file Local file or uploaded file array.
     * @return bool
     */
    public function exists($file)
    {
        return (bool)$this->getFilename($file, false);
    }

    /**
     * Will check if local file exists or just uploaded.
     *
     * @param array|string $file Local file or uploaded file array.
     * @return bool
     */
    public function uploaded($file)
    {
        return (bool)$this->getFilename($file, true);
    }

    /**
     * Checks to see if the filesize is smaller than the minimum size required. The size is set in
     * KBytes.
     *
     * @param array|string $file Local file or uploaded file array.
     * @param int          $size Max filesize in kBytes.
     * @return bool
     */
    public function size($file, $size)
    {
        if (!$file = $this->getFilename($file, false))
        {
            return false;
        }

        return $this->files->size($file) < $size * 1024;
    }

    /**
     * Pass files where public or local name has allowed extensions. This is soft validation, no
     * real guarantee that extension was not manually modified by client.
     *
     * @param array|string $file       Local file or uploaded file array.
     * @param array|mixed  $extensions Array of acceptable extensions.
     * @return bool
     */
    public function extension($file, $extensions)
    {
        if ($file instanceof UploadedFileInterface)
        {
            if (!is_array($extensions))
            {
                $extensions = array_slice(func_get_args(), 1);
            }

            return in_array($this->files->extension($file->getClientFilename()), $extensions);
        }

        return in_array($this->files->extension($file), $extensions);
    }
}