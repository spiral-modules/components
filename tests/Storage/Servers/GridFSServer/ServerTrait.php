<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\Storage\Servers\GridFSServer;

use Spiral\Storage\BucketInterface;
use Spiral\Storage\ServerInterface;

trait ServerTrait
{
    protected function getServer(): ServerInterface
    {
    }

    protected function getBucket(): BucketInterface
    {
    }
}