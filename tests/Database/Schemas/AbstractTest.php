<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\Database\Schemas;

use Spiral\Database\Entities\Database;
use Spiral\Database\Entities\Driver;

abstract class AbstractTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @param string $name
     * @param string $prefix
     *
     * @return Database
     */
    protected function database($name = 'default', $prefix = ''): Database
    {
        return new Database($this->getDriver(), $name, $prefix);
    }

    /**
     * @return Driver
     */
    abstract protected function getDriver(): Driver;

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