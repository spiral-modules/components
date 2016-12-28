<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\Database\Drivers\SQLite;

use Spiral\Database\Drivers\SQLite\Schemas\SQLiteTable;

class BuildersAccessTest extends \Spiral\Tests\Database\Drivers\BuildersAccessTest
{
    use DriverTrait;

    public function testTableSchemaAccess()
    {
        parent::testTableSchemaAccess();
        $this->assertInstanceOf(
            SQLiteTable::class,
            $this->database()->table('sample')->getSchema()
        );
    }
}