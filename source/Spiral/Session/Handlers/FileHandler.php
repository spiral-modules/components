<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Session\Handlers;

use Spiral\Core\Container\SaturableInterlace;
use Spiral\Files\FilesInterface;

/**
 * Stores session data in filename.
 */
class FileHandler implements \SessionHandlerInterface, SaturableInterlace
{
    /**
     * @var string
     */
    protected $location = '';

    /**
     * @var FilesInterface
     */
    protected $files = null;

    /**
     * @param array $options  Session handler options.
     * @param int   $lifetime Default session lifetime.
     */
    public function __construct(array $options, $lifetime = 0)
    {
        $this->location = $options['directory'];
    }

    /**
     * @param FilesInterface $files
     */
    public function saturate(FilesInterface $files)
    {
        $this->files = $files;
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function destroy($session_id)
    {
        return $this->files->delete($this->location . FilesInterface::SEPARATOR . $session_id);
    }

    /**
     * {@inheritdoc}
     */
    public function gc($maxlifetime)
    {
        foreach ($this->files->getFiles($this->location) as $filename) {
            if ($this->files->time($filename) < time() - $maxlifetime) {
                $this->files->delete($filename);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function open($save_path, $session_id)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function read($session_id)
    {
        return $this->files->exists($this->location . FilesInterface::SEPARATOR . $session_id)
            ? $this->files->read($this->location . FilesInterface::SEPARATOR . $session_id)
            : false;
    }

    /**
     * {@inheritdoc}
     */
    public function write($session_id, $session_data)
    {
        try {
            return $this->files->write(
                $this->location . FilesInterface::SEPARATOR . $session_id,
                $session_data
            );
        } catch (\ErrorException $exception) {
            //Possibly that directory doesn't exists, we don't want to force directory by default,
            //but we can try now.
            return $this->files->write(
                $this->location . FilesInterface::SEPARATOR . $session_id,
                $session_data,
                FilesInterface::RUNTIME,
                true
            );
        }
    }
}