<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Views;

use Spiral\Core\ExceptionInterface;

class ViewException extends \RuntimeException implements ExceptionInterface
{
    /**
     * Set exception location. Can be used to force exception caused by invalid view syntax inside
     * view processors.
     *
     * @param string $file
     * @param int    $line
     */
    public function setLocation($file, $line)
    {
        $this->file = $file;
        $this->line = $line;
    }
}