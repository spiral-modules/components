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
    private $driver;

    public function getDriver(): Driver
    {
        return $this->driver ?? $this->driver = new MySQLDriver(
                'mysql',
                [
                    'connection' => 'mysql:host=localhost;dbname=spiral',
                    'username'   => 'root',
                    'password'   => '',
                    'options'    => []
                ],
                new Container()
            );
    }

    protected function driverID(): string
    {
        return 'mysql';
    }
}