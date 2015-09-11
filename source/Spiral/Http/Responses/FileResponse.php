<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Http\Responses;

use Spiral\Http\Response;

/**
 * FileResponse used to create responses associated with local file or stream. Response will set
 * create Content-Type (application/octet-stream) and Content-Length headers.
 */
class FileResponse extends Response
{
    /**
     * @param string $filename Local filename to be send.
     * @param string $name     Filename to be shown to client.
     * @param int    $status
     * @param array  $headers
     */
    public function __construct($filename, $name = null, $status = 200, array $headers = [])
    {
        if (empty($name)) {
            $name = basename($filename);
        }

        //Forcing default set of headers
        $headers += [
            'Content-Disposition'       => 'attachment; filename="' . addcslashes($name, '"') . '"',
            'Content-Transfer-Encoding' => 'binary',
            'Content-Type'              => 'application/octet-stream',
            'Content-Length'            => (string)filesize($filename),
            'Expires'                   => '0',
            'Cache-Control'             => 'no-cache, must-revalidate',
            'Pragma'                    => 'public'
        ];

        parent::__construct(fopen($filename, 'rb'), $status, $headers);
    }
}