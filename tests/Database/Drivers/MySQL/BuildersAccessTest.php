<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\Database\Drivers\MySQL;

use Spiral\Database\Drivers\MySQL\Schemas\MySQLTable;

class BuildersAccessTest extends \Spiral\Tests\Database\Drivers\BuildersAccessTest
{
    use DriverTrait;

    public function testTableSchemaAccess()
    {
        parent::testTableSchemaAccess();
        $this->assertInstanceOf(
            MySQLTable::class,
            $this->database()->table('sample')->getSchema()
        );
    }
}