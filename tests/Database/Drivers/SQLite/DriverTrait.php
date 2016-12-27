<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\Database\Drivers\SQLite;

use Spiral\Core\Container;
use Spiral\Database\Drivers\SQLite\SQLiteDriver;
use Spiral\Database\Entities\Driver;

trait DriverTrait
{
    private $driver;

    public function getDriver(): Driver
    {
        return $this->driver ?? $this->driver = new SQLiteDriver(
                'sqlite',
                [
                    'connection' => 'sqlite:' . __DIR__ . '/fixture/runtime.db',
                    'username'   => 'sqlite',
                    'password'   => '',
                    'options'    => []
                ],
                new Container()
            );
    }

    protected function driverID(): string
    {
        return 'sqlite';
    }
}