<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\Database\Drivers\MySQL;

use Spiral\Core\Container;
use Spiral\Database\Drivers\MySQL\MySQLDriver;
use Spiral\Database\Entities\Driver;

trait DriverTrait
{
    public function getDriver(): Driver
    {
        return new MySQLDriver(
            'mysql',
            [
                'connection' => 'mysql:host=localhost;dbname=phpunit',
                'username'   => 'root',
                'password'   => '',
                'options'    => []
            ],
            new Container()
        );
    }
}