<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\Database\Drivers;

use Spiral\Database\Entities\Database;
use Spiral\Database\Entities\Driver;
use Spiral\Database\Schemas\Prototypes\AbstractTable;
use Spiral\Database\Schemas\StateComparator;

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

    protected function assertSameAsInDB(AbstractTable $current)
    {
        $comparator = new StateComparator(
            $current->getState(),
            $this->schema($current->getName())->getState()
        );

        if ($comparator->hasChanges()) {
            $this->fail($this->makeMessage($current->getName(), $comparator));
        }
    }

    protected function makeMessage(string $table, StateComparator $comparator)
    {
        if ($comparator->isPrimaryChanged()) {
            return "Table '{$table}' not synced, primary indexes are different.";
        }

        if ($comparator->droppedColumns()) {
            return "Table '{$table}' not synced, columns are missing.";
        }

        if ($comparator->addedColumns()) {
            return "Table '{$table}' not synced, new columns found.";
        }

        if ($comparator->alteredColumns()) {

            $names = [];
            foreach ($comparator->alteredColumns() as $pair) {
                $names[] = $pair[0]->getName();
            }

            return "Table '{$table}' not synced, column(s) '" . join("', '", $names) . "' have been changed.";
        }

        return "Table '{$table}' not synced, no idea why, add more messages :P";
    }
}