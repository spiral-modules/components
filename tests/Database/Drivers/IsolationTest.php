<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\Database\Drivers;

use Spiral\Database\Entities\AbstractHandler;
use Spiral\Database\Entities\Database;
use Spiral\Database\Schemas\Prototypes\AbstractTable;

abstract class IsolationTest extends BaseTest
{
    public function tearDown()
    {
        $this->dropAll($this->database());
    }

    public function schema(string $prefix, string $table): AbstractTable
    {
        $db = new Database($this->getDriver(), $prefix, $prefix);

        return $db->table($table)->getSchema();
    }

    public function testGetPrefix()
    {
        $schema = $this->schema('prefix_', 'table');
        $this->assertFalse($schema->exists());

        $this->assertSame('prefix_', $schema->getPrefix());
        $this->assertSame('prefix_table', $schema->getName());

        $schema->primary('id');
        $schema->save(AbstractHandler::DO_ALL);

        $this->assertTrue($this->schema('prefix_', 'table')->exists());
    }

    public function testSetPrefix()
    {
        $schema = $this->schema('prefix_', 'table');
        $this->assertFalse($schema->exists());

        $this->assertSame('prefix_', $schema->getPrefix());
        $this->assertSame('prefix_table', $schema->getName());

        $schema->setName('new_name');
        $this->assertSame('prefix_new_name', $schema->getName());

        $schema->primary('id');
        $schema->save(AbstractHandler::DO_ALL);

        $this->assertTrue($this->schema('prefix_', 'new_name')->exists());
        $this->assertTrue($this->schema('prefix_new_', 'name')->exists());
    }

    public function testRenamePrefixed()
    {
        $schema = $this->schema('prefix_', 'table');
        $this->assertFalse($schema->exists());

        $schema->primary('id');
        $schema->save(AbstractHandler::DO_ALL);

        $this->assertTrue($this->schema('prefix_', 'table')->exists());

        $schema->setName('abc');
        $schema->save(AbstractHandler::DO_ALL);

        $this->assertFalse($this->schema('prefix_', 'table')->exists());
        $this->assertTrue($this->schema('prefix_', 'abc')->exists());
    }
}