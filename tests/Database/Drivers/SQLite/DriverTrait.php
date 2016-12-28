<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\Database\Drivers\SQLite;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;
use Spiral\Core\Container;
use Spiral\Database\Drivers\SQLite\SQLiteDriver;
use Spiral\Database\Entities\Driver;

//@todo investigate issue about renaming tables cause 17 error in SQLite on schema read!
trait DriverTrait
{
    private $driver;

    public function getDriver(): Driver
    {
        //Issues with SQL getting calls from multiple tests
        $db = md5(get_called_class());

        //$driver = $this->driver ??
        $this->driver = new SQLiteDriver(
            'sqlite',
            [
                'connection' => 'sqlite:' . __DIR__ . '/fixture/' . $db . '.db',
                'username'   => 'sqlite',
                'password'   => '',
                'options'    => []
            ],
            new Container()

        );

        $this->driver->setProfiling(static::PROFILING)->setLogger(new class implements LoggerInterface
        {
            use LoggerTrait;

            public function log($level, $message, array $context = [])
            {
                if ($level == LogLevel::ERROR) {
                    echo " \n! \033[31m" . $message . "\033[0m";
                } elseif ($level == LogLevel::ALERT) {
                    echo " \n! \033[35m" . $message . "\033[0m";
                } elseif (strpos($message, 'PRAGMA') === 0) {
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

        return $this->driver;
    }

    protected function driverID(): string
    {
        return 'sqlite';
    }
}