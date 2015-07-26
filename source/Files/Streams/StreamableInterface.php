<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Files\Streams;

use Psr\Http\Message\StreamInterface;

interface StreamableInterface
{
    /**
     * Get associated stream.
     *
     * @return StreamInterface
     */
    public function getStream();
}