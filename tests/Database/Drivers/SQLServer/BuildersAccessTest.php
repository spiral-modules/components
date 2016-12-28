<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\Database\Drivers\SQLServer;

use Spiral\Database\Drivers\SQLServer\Schemas\SQLServerTable;

class BuildersAccessTest extends \Spiral\Tests\Database\Drivers\BuildersAccessTest
{
    use DriverTrait;

    public function testTableSchemaAccess()
    {
        parent::testTableSchemaAccess();
        $this->assertInstanceOf(
            SQLServerTable::class,
            $this->database()->table('sample')->getSchema()
        );
    }
}