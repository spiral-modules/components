<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Files;

use Spiral\Core\Singleton;

/**
 * Default files storage, points to local hard drive.
 */
class FileManager extends Singleton implements FilesInterface
{
    /**
     * Declares to IoC that component instance should be treated as singleton.
     */
    const SINGLETON = self::class;

    /**
     * Files to be removed when component destructed.
     *
     * @var array
     */
    private $destruct = [];

    /**
     * New File Manager.
     */
    public function __construct()
    {
        //Safety mechanism
        register_shutdown_function([$this, '__destruct']);
    }

    /**
     * {@inheritdoc}
     *
     * @param bool $recursively Every created directory will get specified permissions.
     */
    public function ensureLocation($location, $mode = self::RUNTIME, $recursive = true)
    {
        $mode = $mode | 0111;
        if (is_dir($location))
        {
            //Exists :(
            return $this->setPermissions($location, $mode);
        }

        if (!$recursive)
        {
            return mkdir($location, $mode, true);
        }

        $directoryChain = [basename($location)];

        $baseDirectory = $location;
        while (!is_dir($baseDirectory = dirname($baseDirectory)))
        {
            $directoryChain[] = basename($baseDirectory);
        }

        foreach (array_reverse($directoryChain) as $directory)
        {
            if (!mkdir($baseDirectory = $baseDirectory . '/' . $directory))
            {
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
        return file_get_contents($filename);
    }

    /**
     * {@inheritdoc}
     *
     * @param bool $append To append data at the end of existed file.
     */
    public function write($filename, $data, $mode = null, $ensureLocation = false, $append = false)
    {
        $ensureLocation && $this->ensureLocation(dirname($filename), $mode);

        if (!empty($mode) && $this->exists($filename))
        {
            //Forcing mode for existed file
            $this->setPermissions($filename, $mode);
        }

        $result = (file_put_contents(
                $filename, $data, $append ? FILE_APPEND | LOCK_EX : LOCK_EX
            ) !== false);

        if ($result && !empty($mode))
        {
            //Forcing mode after file creation
            $this->setPermissions($filename, $mode);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function append($filename, $data, $mode = null, $ensureLocation = false)
    {
        return $this->write($filename, $data, $mode, $ensureLocation, true);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($filename)
    {
        if ($this->exists($filename))
        {
            return unlink($filename);
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function move($filename, $destination)
    {
        return rename($filename, $destination);
    }

    /**
     * {@inheritdoc}
     */
    public function copy($filename, $destination, $ensureLocation = false)
    {
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
        if (!$this->exists($filename))
        {
            return false;
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
        if (!$this->exists($filename))
        {
            return false;
        }

        return md5_file($filename);
    }

    /**
     * {@inheritdoc}
     */
    public function time($filename)
    {
        if (!$this->exists($filename))
        {
            return false;
        }

        return filemtime($filename);
    }

    /**
     * {@inheritdoc}
     */
    public function getPermissions($filename)
    {
        if (!$this->exists($filename))
        {
            return false;
        }

        return fileperms($filename) & 0777;
    }

    /**
     * {@inheritdoc}
     */
    public function setPermissions($filename, $mode)
    {
        if (is_dir($filename))
        {
            $mode |= 0111;
        }

        return $this->getPermissions($filename) == $mode || chmod($filename, $mode);
    }

    /**
     * {@inheritdoc}
     */
    public function getFiles($location, $extension = null, &$result = [])
    {
        if (is_string($extension))
        {
            $extension = [$extension];
        }

        $location = $this->normalizePath($location) . static::SEPARATOR;

        $glob = glob($location . '*');
        foreach ($glob as $item)
        {
            if (is_dir($item))
            {
                $this->getFiles($item . '/', $extension, $result);
                continue;
            }

            if (!empty($extension) && !in_array($this->extension($item), $extension))
            {
                continue;
            }

            $result[] = $this->normalizePath($item);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function tempFilename($extension = '', $location = null)
    {
        if (!empty($location))
        {
            $location = sys_get_temp_dir();
        }

        $filename = tempnam($location, 'spiral');

        if ($extension)
        {
            //I should probably find more original way of doing that
            rename($filename, $filename = $filename . '.' . $extension);
            $this->destruct[] = $filename;
        }

        return $filename;
    }

    /**
     * {@inheritdoc}
     */
    public function normalizePath($path)
    {
        $path = str_replace('\\', '/', $path);

        return rtrim(str_replace('//', '/', $path), '/');
    }

    /**
     * Get relative location based on absolute path.
     *
     * @link http://stackoverflow.com/questions/2637945/getting-relative-path-from-absolute-path-in-php
     * @param string $path      Original file or directory location.
     * @param string $directory Path will be converted to be relative to this directory.
     * @return string
     */
    public function relativePath($path, $directory = null)
    {
        //Always directory
        $directory = $this->normalizePath($directory) . '/';
        $path = $this->normalizePath($path);

        if (is_dir($path))
        {
            $path = rtrim($path, '/') . '/';
        }

        $directory = explode('/', $directory);
        $path = explode('/', $path);

        $relPath = $path;
        foreach ($directory as $depth => $nested)
        {
            //Find first non-matching directory
            if ($nested === $path[$depth])
            {
                //Ignore this directory
                array_shift($relPath);
            }
            else
            {
                //Get number of remaining dirs to $from
                $remaining = count($nested) - $depth;
                if ($remaining > 1)
                {
                    // add traversals up to first matching dir
                    $padLength = (count($relPath) + $remaining - 1) * -1;
                    $relPath = array_pad($relPath, $padLength, '..');
                    break;
                }
                else
                {
                    $relPath[0] = './' . $relPath[0];
                }
            }
        }

        return implode('/', $relPath);
    }

    /**
     * Destruct every temporary file.
     */
    public function __destruct()
    {
        foreach ($this->destruct as $filename)
        {
            $this->delete($filename);
        }
    }
}