<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Files;

use Spiral\Core\Component;
use Spiral\Core\Container\SingletonInterface;
use Spiral\Files\Exceptions\FileNotFoundException;
use Spiral\Files\Exceptions\FilesException;
use Spiral\Files\Exceptions\WriteErrorException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\Iterator\RecursiveDirectoryIterator;

/**
 * Default files storage, points to local hard drive.
 */
class FileManager extends Component implements SingletonInterface, FilesInterface
{
    /**
     * Files to be removed when component destructed.
     *
     * @var array
     */
    private $destruct = [];

    /**
     * New File Manager.
     *
     * @todo Potentially can be depended on Symfony Filesystem.
     */
    public function __construct()
    {
        //Safety mechanism
        register_shutdown_function([$this, '__destruct']);
    }

    /**
     * {@inheritdoc}
     *
     * @param bool $recursive Every created directory will get specified permissions.
     */
    public function ensureDirectory($directory, $mode = self::RUNTIME, $recursive = true)
    {
        $mode = $mode | 0111;
        if (is_dir($directory)) {
            //Exists :(
            return $this->setPermissions($directory, $mode);
        }

        if (!$recursive) {
            return mkdir($directory, $mode, true);
        }

        $directoryChain = [basename($directory)];

        $baseDirectory = $directory;
        while (!is_dir($baseDirectory = dirname($baseDirectory))) {
            $directoryChain[] = basename($baseDirectory);
        }

        foreach (array_reverse($directoryChain) as $directory) {
            if (!mkdir($baseDirectory = $baseDirectory . '/' . $directory)) {
                return false;
            }

            chmod($baseDirectory, $mode);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function read($filename)
    {
        if (!$this->exists($filename)) {
            throw new FileNotFoundException($filename);
        }

        return file_get_contents($filename);
    }

    /**
     * {@inheritdoc}
     *
     * @param bool $append To append data at the end of existed file.
     */
    public function write($filename, $data, $mode = null, $ensureDirectory = false, $append = false)
    {
        try {
            if ($ensureDirectory) {
                $this->ensureDirectory(dirname($filename), $mode);
            }

            if (!empty($mode) && $this->exists($filename)) {
                //Forcing mode for existed file
                $this->setPermissions($filename, $mode);
            }

            $result = (file_put_contents(
                    $filename, $data, $append ? FILE_APPEND | LOCK_EX : LOCK_EX
                ) !== false);

            if ($result && !empty($mode)) {
                //Forcing mode after file creation
                $this->setPermissions($filename, $mode);
            }
        } catch (\ErrorException $exception) {
            throw new WriteErrorException(
                $exception->getMessage(),
                $exception->getCode(),
                $exception
            );
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function append($filename, $data, $mode = null, $ensureDirectory = false)
    {
        return $this->write($filename, $data, $mode, $ensureDirectory, true);
    }

    /**
     * {@inheritdoc}
     */
    public function localUri($filename)
    {
        if (!$this->exists($filename)) {
            throw new FileNotFoundException($filename);
        }

        //Since default implementation is local we are allowed to do that
        return $filename;
    }

    /**
     * {@inheritdoc}
     */
    public function delete($filename)
    {
        if ($this->exists($filename)) {
            return unlink($filename);
        }

        return false;
    }

    /**
     * {@inheritdoc}
     *
     * @see http://stackoverflow.com/questions/3349753/delete-directory-with-files-in-it
     * @param string $directory
     * @param bool   $contentOnly
     * @throws FilesException
     */
    public function deleteDirectory($directory, $contentOnly = false)
    {
        if (!$this->isDirectory($directory)) {
            throw new FilesException("Undefined or invalid directory {$directory}");
        }

        $files = new \RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                $this->delete($file->getRealPath());
            }
        }

        if (!$contentOnly) {
            rmdir($directory);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function move($filename, $destination)
    {
        if (!$this->exists($filename)) {
            throw new FileNotFoundException($filename);
        }

        return rename($filename, $destination);
    }

    /**
     * {@inheritdoc}
     */
    public function copy($filename, $destination)
    {
        if (!$this->exists($filename)) {
            throw new FileNotFoundException($filename);
        }

        return copy($filename, $destination);
    }

    /**
     * {@inheritdoc}
     */
    public function touch($filename, $mode = null, $ensureLocation = false)
    {
        return touch($filename);
    }

    /**
     * {@inheritdoc}
     */
    public function exists($filename)
    {
        return file_exists($filename);
    }

    /**
     * {@inheritdoc}
     */
    public function size($filename)
    {
        if (!$this->exists($filename)) {
            throw new FileNotFoundException($filename);
        }

        return filesize($filename);
    }

    /**
     * {@inheritdoc}
     */
    public function extension($filename)
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    /**
     * {@inheritdoc}
     */
    public function md5($filename)
    {
        if (!$this->exists($filename)) {
            throw new FileNotFoundException($filename);
        }

        return md5_file($filename);
    }

    /**
     * {@inheritdoc}
     */
    public function time($filename)
    {
        if (!$this->exists($filename)) {
            throw new FileNotFoundException($filename);
        }

        return filemtime($filename);
    }

    /**
     * {@inheritdoc}
     */
    public function isDirectory($filename)
    {
        return is_dir($filename);
    }

    /**
     * {@inheritdoc}
     */
    public function isFile($filename)
    {
        return is_file($filename);
    }

    /**
     * {@inheritdoc}
     */
    public function getPermissions($filename)
    {
        if (!$this->exists($filename)) {
            throw new FileNotFoundException($filename);
        }

        return fileperms($filename) & 0777;
    }

    /**
     * {@inheritdoc}
     */
    public function setPermissions($filename, $mode)
    {
        if (is_dir($filename)) {
            $mode |= 0111;
        }

        return $this->getPermissions($filename) == $mode || chmod($filename, $mode);
    }

    /**
     * {@inheritdoc}
     *
     * @param Finder $finder Optional initial finder.
     */
    public function getFiles($location, $pattern = null, Finder $finder = null)
    {
        if (empty($finder)) {
            $finder = new Finder();
        }

        $finder->files()->in($location);

        if (!empty($pattern)) {
            $finder->name($pattern);
        }

        $result = [];
        foreach ($finder->getIterator() as $file) {
            $result[] = $this->normalizePath((string)$file);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function tempFilename($extension = '', $location = null)
    {
        if (!empty($location)) {
            $location = sys_get_temp_dir();
        }

        $filename = tempnam($location, 'spiral');

        if ($extension) {
            //I should find more original way of doing that
            rename($filename, $filename = $filename . '.' . $extension);
            $this->destruct[] = $filename;
        }

        return $filename;
    }

    /**
     * {@inheritdoc}
     */
    public function normalizePath($path, $directory = false)
    {
        $path = str_replace('\\', '/', $path);

        //Potentially open links and ../ type directories?
        return rtrim(preg_replace('/\/+/', '/', $path), '/') . ($directory ? '/' : '');
    }

    /**
     * {@inheritdoc}
     *
     * @link http://stackoverflow.com/questions/2637945/getting-relative-path-from-absolute-path-in-php
     */
    public function relativePath($path, $from)
    {
        $path = $this->normalizePath($path);
        $from = $this->normalizePath($from);

        $from = explode('/', $from);
        $path = explode('/', $path);
        $relative = $path;

        foreach ($from as $depth => $dir) {
            //Find first non-matching dir
            if ($dir === $path[$depth]) {
                //Ignore this directory
                array_shift($relative);
            } else {
                //Get number of remaining dirs to $from
                $remaining = count($from) - $depth;
                if ($remaining > 1) {
                    //Add traversals up to first matching directory
                    $padLength = (count($relative) + $remaining - 1) * -1;
                    $relative = array_pad($relative, $padLength, '..');
                    break;
                } else {
                    $relative[0] = './' . $relative[0];
                }
            }
        }

        return implode('/', $relative);
    }

    /**
     * Destruct every temporary file.
     */
    public function __destruct()
    {
        foreach ($this->destruct as $filename) {
            $this->delete($filename);
        }
    }
}