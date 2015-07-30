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


class FileResponse extends Response
{
    /**
     * FileResponse used to create responses associated with local file stream. Response will automatically
     * create Content-Type (application/octet-stream) and Content-Length headers. Headers can be
     * rewritten manually.
     *
     * @param string $filename Local filename to be send.
     * @param string $name     Name show to client.
     * @param int    $status
     * @param array  $headers
     */
    public function __construct($filename, $name = null, $status = 200, array $headers = [])
    {
        if (!$name)
        {
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