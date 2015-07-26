<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Files;

use Psr\Http\Message\UploadedFileInterface;
use Spiral\Core\Traits;

interface FilesInterface
{
    /**
     * Default file permissions is 777, this files are fully writable and readable
     * by all application environments. Usually this files stored under application/data folder,
     * however they can be in some other public locations.
     */
    const RUNTIME = 0777;

    /**
     * Work files are files which created by or for framework, like controllers, configs and config
     * directories. This means that only CLI mode application can modify them. You should not create
     * work files from web application.
     */
    const READONLY = 0666;

    /**
     * A simple alias for file_get_contents, no real reason for using it, only to keep code clean.
     *
     * @param string $filename
     * @return string
     */
    public function read($filename);

    /**
     * Write file to specified directory, and update file permissions if necessary. Function can
     * additionally ensure that target file directory exists.
     *
     * @param string $filename
     * @param string $data            String data to write, can contain binary data.
     * @param int    $mode            Use File::RUNTIME for 777
     * @param bool   $ensureDirectory If true, helper will ensure that destination directory exists
     *                                and have right permissions.
     * @return bool
     */
    public function write($filename, $data, $mode = null, $ensureDirectory = false);

    /**
     * Will try to remove file. No exception will be thrown if file no exists.
     *
     * @see delete()
     * @param string $filename
     * @return bool
     */
    public function delete($filename);

    /**
     * Move a file to a new location.
     *
     * @see rename()
     * @param string $filename
     * @param string $destination
     * @return bool
     */
    public function move($filename, $destination);

    /**
     * Copy file to new location.
     *
     * @see copy()
     * @param string $filename
     * @param string $destination
     * @return bool
     */
    public function copy($filename, $destination);

    /**
     * Sets access and modification time of file. File will be automatically created on touch.
     *
     * @param string $filename
     * @return bool
     */
    public function touch($filename);

    /**
     * Check if file exists.
     *
     * @param string $filename
     * @return bool
     */
    public function exists($filename);

    /**
     * Get filesize in bytes if file exists.
     *
     * @param string $filename
     * @return int
     */
    public function size($filename);

    /**
     * Will chunk extension from file name.
     *
     * @param string $filename
     * @return bool
     */
    public function extension($filename);

    /**
     * Get file MD5 hash.
     *
     * @param string $filename
     * @return bool
     */
    public function md5($filename);

    /**
     * This function returns the time when the data blocks of a file were being written to, that is,
     * the time when the content of the file was changed.
     *
     * @link http://php.net/manual/en/function.filemtime.php
     * @param string $filename
     * @return int
     */
    public function timeUpdated($filename);

    /**
     * File permissions with 777 binary mask.
     *
     * @param string $filename
     * @return int
     */
    public function getPermissions($filename);

    /**
     * Change file permission mode.
     *
     * @param string $filename
     * @param string $mode Use File::RUNTIME for 666
     * @return bool
     */
    public function setPermissions($filename, $mode);

    /**
     * Check if provided file were uploaded, is_uploaded_file() function will be used to check it.
     *
     * @param mixed|UploadedFileInterface $file Filename or file array.
     * @return bool
     */
    public function isUploaded($file);

    /**
     * Will read all available files from specified directory, including nested directories and files.
     * Will not include empty directories to list. You can specify to exclude some files by their
     * extension, for example to find only php files.
     *
     * @param string     $directory  Root directory to index.
     * @param null|array $extensions Array of extensions to include to indexation. Any other extension
     *                               will be ignored.
     * @param array      $result
     * @return array
     */
    public function getFiles($directory, $extensions = null, &$result = []);

    /**
     * Will create temporary unique file with desired extension, by default no extension will be used
     * and default tempnam() function will be used. You can specify temp directory where file should
     * be created, by default /tmp will be used. Make sure this directory is available for writing
     * for php process.
     *
     * File prefix can be used to identify files created under multiple applications, make sure that
     * prefix should follow same rules as for tempnam() function.
     *
     * @param string $extension Desired file extension, empty (no extension) by default.
     * @param string $directory Directory where file should be created, false (system temp dir) by
     *                          default.
     * @param string $prefix    File prefix, "sp" by default.
     * @return string
     */
    public function tempFilename($extension = '', $directory = null, $prefix = 'sp');

    /**
     * Getting relative location based on absolute path.
     *
     * @link http://stackoverflow.com/questions/2637945/getting-relative-path-from-absolute-path-in-php
     * @param string $location   Original file or directory location.
     * @param string $relativeTo Path will be converted to be relative to this directory. By default
     *                           application root directory will be used.
     * @return string
     */
    public function relativePath($location, $relativeTo = null);

    /**
     * Will normalize directory of file path to unify it (using UNIX directory separator /), windows
     * symbol "\" requires escaping and not very "visual" for identifying files. This function will
     * always remove end slash for path (even for directories).
     *
     * @param string $path      File or directory path.
     * @param bool   $directory Force end slash for directory path.
     * @return string
     */
    public function normalizePath($path, $directory = false);

    /**
     * Make sure directory exists and has right permissions, works recursively.
     *
     * @param string $directory            Target directory.
     * @param mixed  $mode                 Use File::RUNTIME for 777
     * @param bool   $recursivePermissions Use this flag to apply permissions to all *created*
     *                                     directories. This flag used by system to ensure that all
     *                                     files and folders in runtime directory has right permissions,
     *                                     and by local storage server due it can create sub folders.
     *                                     This is slower by safer than using umask().
     * @return bool
     */
    public function ensureDirectory($directory, $mode = self::RUNTIME, $recursivePermissions = true);
}