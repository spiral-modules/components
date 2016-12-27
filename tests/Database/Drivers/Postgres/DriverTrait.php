<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\Database\Drivers\Postgres;

use Spiral\Core\Container;
use Spiral\Database\Drivers\Postgres\PostgresDriver;
use Spiral\Database\Entities\Driver;

trait DriverTrait
{
    private $driver;

    public function getDriver(): Driver
    {
        return $this->driver ?? $this->driver = new PostgresDriver(
                'mysql',
                [
                    'connection' => 'pgsql:host=127.0.0.1;dbname=spiral',
                    'username'   => 'postgres',
                    'password'   => 'postgres',
                    'options'    => []
                ],
                new Container()
            );
    }

    protected function driverID(): string
    {
        return 'postgres';
    }
}