<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Debug\Logger\Handlers;

use Spiral\Core\Component;
use Spiral\Core\Container\SaturableInterface;
use Spiral\Core\Traits\SaturateTrait;
use Spiral\Debug\Logger\HandlerInterface;
use Spiral\Files\FilesInterface;

/**
 * Write log message to specified file and rotates this file with prefix when max size exceed.
 */
class FileHandler extends Component implements HandlerInterface
{
    /**
     * Additional constructor arguments.
     */
    use SaturateTrait;

    /**
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
     * @var FilesInterface
     */
    protected $files = null;

    /**
     * {@inheritdoc}
     */
    public function __construct(array $options, FilesInterface $files = null)
    {
        $this->options = $options + $this->options;

        //We can use global container as fallback if no default values were provided
        $this->files = $this->saturate($files, FilesInterface::class);
    }

    /**
     * Handle log message.
     *
     * @param int    $level   Log message level.
     * @param string $message Message.
     * @param array  $context Context data.
     */
    public function __invoke($level, $message, array $context = [])
    {
        $message = \Spiral\interpolate($this->options['format'], [
            'date'    => date($this->options['dateFormat'], time()),
            'level'   => $level,
            'message' => $message
        ]);

        if ($this->files->append($this->options['filename'], "{$message}\n", $this->options['mode'],
            true)
        ) {
            if ($this->files->size($this->options['filename']) > $this->options['filesize']) {
                $this->files->move(
                    $this->options['filename'],
                    $this->options['filename'] . $this->options['rotatePostfix']
                );
            }
        }
    }
}