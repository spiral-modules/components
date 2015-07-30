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

/**
 * Class contain PSR-7 compatible body.
 */
interface StreamableInterface
{
    /**
     * @return StreamInterface
     */
    public function getStream();
}