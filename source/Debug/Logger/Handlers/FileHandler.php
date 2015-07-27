<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Debug\Logger\Handlers;

use Spiral\Debug\Logger\HandlerInterface;
use Spiral\Files\FilesInterface;

class FileHandler implements HandlerInterface
{
    /**
     * FileHandler options.
     *
     * @var array
     */
    protected $options = [
        'filename'      => '',
        'filesize'      => 2097152,
        'mode'          => FilesInterface::RUNTIME,
        'rotatePostfix' => '.old',
        'format'        => '{date}: [{level}] {message}',
        'dateFormat'    => 'H:i:s d.m.Y'
    ];

    /**
     * Files component. Will not work if not specified.
     *
     * @var FilesInterface|null
     */
    protected $files = null;

    /**
     * HandlerInterface should only accept options from debug, due it's going to be created using
     * container you can declare any additional dependencies you want.
     *
     * @param array          $options
     * @param FilesInterface $files
     */
    public function __construct(array $options, FilesInterface $files = null)
    {
        $this->options = $options + $this->options;
        $this->files = $files;
    }

    /**
     * Handle log message.
     *
     * @param int    $level   Log message level.
     * @param string $message Message.
     * @param array  $context Context data.
     */
    public function __invoke($level, $message, array $context)
    {
        if (empty($this->files) || empty($this->options['filename']))
        {
            return;
        }

        $message = \Spiral\interpolate($this->options['format'], [
            'date'    => date($this->options['dateFormat'], time()),
            'level'   => $level,
            'message' => $message
        ]);

        if ($this->files->append($this->options['filename'], $message, $this->options['mode'], true))
        {
            if ($this->files->size($this->options['filename']) > $this->options['filesize'])
            {
                $this->files->move(
                    $this->options['filename'],
                    $this->options['filename'] . $this->options['rotatePostfix']
                );
            }
        }
    }
}