<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\Database\Drivers;

use Spiral\Database\Entities\Database;
use Spiral\Database\Entities\Driver;

abstract class AbstractTest extends \PHPUnit_Framework_TestCase
{
    static $driversCache = [];

    /**
     * @param string $name
     * @param string $prefix
     *
     * @return Database
     */
    protected function database($name = 'default', $prefix = ''): Database
    {
        if (isset(self::$driversCache[$this->driverID()])) {
            $driver = self::$driversCache[$this->driverID()];
        } else {
            self::$driversCache[$this->driverID()] = $driver = $this->getDriver();
        }

        return new Database($driver, $name, $prefix);
    }

    /**
     * @return Driver
     */
    abstract protected function getDriver(): Driver;

    /**
     * @return string
     */
    abstract protected function driverID(): string;

    /**
     * Drop all tables in db.
     */
    protected function dropAll(Database $database)
    {
        foreach ($database->getTables() as $table) {
            $schema = $table->getSchema();
            $schema->declareDropped();
            $schema->save();
        }
    }
}