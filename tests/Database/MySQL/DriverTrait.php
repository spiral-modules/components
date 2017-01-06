<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\Database\MySQL;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;
use Spiral\Core\Container;
use Spiral\Database\Drivers\MySQL\MySQLDriver;
use Spiral\Database\Entities\Driver;

trait DriverTrait
{
    private $driver;

    public function setUp()
    {
        if (!in_array('mysql', \PDO::getAvailableDrivers())) {
            $this->markTestSkipped(
                'The MySQL PDO extension is not available.'
            );
        }

        parent::setUp();
    }

    public function getDriver(): Driver
    {
        if (!isset($this->driver)) {
            $this->driver = new MySQLDriver(
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

        $driver = $this->driver;

        if (static::PROFILING) {
            $driver->setProfiling(static::PROFILING)->setLogger(new class implements LoggerInterface
            {
                use LoggerTrait;

                public function log($level, $message, array $context = [])
                {
                    if ($level == LogLevel::ERROR) {
                        echo " \n! \033[31m" . $message . "\033[0m";
                    } elseif ($level == LogLevel::ALERT) {
                        echo " \n! \033[35m" . $message . "\033[0m";
                    } elseif (strpos($message, 'SHOW') === 0) {
                        echo " \n> \033[34m" . $message . "\033[0m";
                    } else {
                        if (strpos($message, 'SELECT') === 0) {
                            echo " \n> \033[32m" . $message . "\033[0m";
                        } else {
                            echo " \n> \033[33m" . $message . "\033[0m";
                        }
                    }
                }
            });
        }

        return $driver;
    }

    protected function driverID(): string
    {
        return 'mysql';
    }
}