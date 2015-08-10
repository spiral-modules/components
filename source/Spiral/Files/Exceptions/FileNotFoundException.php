<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Files\Exceptions;

/**
 * When trying to read missing file.
 */
class FileNotFoundException extends FilesException
{
    /**
     * @param string $filename
     */
    public function __construct($filename)
    {
        parent::__construct("File '{$filename}' not found.");
    }
}