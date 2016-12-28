<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\Database\Drivers;

use Spiral\Database\Helpers\SynchronizationPool;
use Spiral\Database\Schemas\Prototypes\AbstractTable;

abstract class SynchronizationPoolTest extends BaseTest
{
    public function tearDown()
    {
        $this->dropAll($this->database());
    }

    public function schema(string $table, string $prefix = ''): AbstractTable
    {
        return $this->database('default', $prefix)->table($table)->getSchema();
    }

    public function testCreateNotLinkedTables()
    {
        $schemaA = $this->schema('a');
        $this->assertFalse($schemaA->exists());

        $schemaB = $this->schema('b');
        $this->assertFalse($schemaB->exists());

        $schemaA->primary('id');
        $schemaA->integer('value');

        $schemaB->primary('id');
        $schemaB->string('value');

        $this->saveTables([$schemaA, $schemaB]);

        $this->assertSameAsInDB($schemaA);
        $this->assertSameAsInDB($schemaB);
    }

    public function testCreateLinkedTablesDirectOrder()
    {
        $schemaA = $this->schema('a');
        $this->assertFalse($schemaA->exists());

        $schemaB = $this->schema('b');
        $this->assertFalse($schemaB->exists());

        $schemaA->primary('id');
        $schemaA->integer('value');

        $schemaB->primary('id');
        $schemaB->string('value');
        $schemaB->integer('a_id');
        $schemaB->foreign('a_id')->references('a', 'id');

        $this->saveTables([$schemaA, $schemaB]);

        $this->assertSameAsInDB($schemaA);
        $this->assertSameAsInDB($schemaB);
    }

    public function testCreateLinkedTablesReversedOrder()
    {
        $schemaA = $this->schema('a');
        $this->assertFalse($schemaA->exists());

        $schemaB = $this->schema('b');
        $this->assertFalse($schemaB->exists());

        $schemaA->primary('id');
        $schemaA->integer('value');
        $schemaB->integer('b_id');
        $schemaB->foreign('b_id')->references('b', 'id');

        $schemaB->primary('id');
        $schemaB->string('value');

        $this->saveTables([$schemaA, $schemaB]);

        $this->assertSameAsInDB($schemaA);
        $this->assertSameAsInDB($schemaB);
    }

    //todo: check changes
    //todo: check drop foreigns
    //todo: check indexes
    //todo: check rename

    protected function saveTables(array $tables)
    {
        $pool = new SynchronizationPool($tables);
        $this->assertSame($tables, $pool->getTables());
        $pool->run();
    }
}