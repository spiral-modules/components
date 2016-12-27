<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\Database\Drivers\SQLServer;

use Spiral\Core\Container;
use Spiral\Database\Drivers\SQLServer\SQLServerDriver;
use Spiral\Database\Entities\Driver;

trait DriverTrait
{
    public function getDriver(): Driver
    {
        return new SQLServerDriver(
            'mysql',
            [
                'connection' => 'sqlsrv:Server=WOLFY-PC;Database=spiral',
                'username'   => '',
                'password'   => '',
                'options'    => []
            ],
            new Container()
        );
    }
}