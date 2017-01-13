<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Commands;

use Spiral\ORM\CommandInterface;

class NullCommand implements CommandInterface
{
    public function execute()
    {
        //nothing to do
    }

    public function complete()
    {
        //nothing to do
    }

    public function rollBack()
    {
        //nothing to do
    }
}