<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\Database\Drivers;

use Spiral\Database\Entities\Database;
use Spiral\Database\Schemas\Prototypes\AbstractTable;

abstract class TransactionsTest extends BaseTest
{
    /**
     * @var Database
     */
    protected $database;

    public function setUp()
    {
        $this->database = $this->database();

        $schema = $this->database->table('table')->getSchema();
        $schema->primary('id');
        $schema->text('name');
        $schema->integer('value');
        $schema->save();
    }

    public function schema(string $table): AbstractTable
    {
        return $this->database->table($table)->getSchema();
    }

    public function tearDown()
    {
        $this->dropAll($this->database());
    }

    public function testCommitTransactionInsert()
    {
        $this->database->begin();

        $this->database->table->insertOne(['name' => 'Anton', 'value' => 123]);
        $this->assertSame(1, $this->database->table->count());

        $this->database->commit();

        $this->assertSame(1, $this->database->table->count());
    }

    public function testRollbackTransactionInsert()
    {
        $this->database->begin();

        $this->database->table->insertOne(['name' => 'Anton', 'value' => 123]);
        $this->assertSame(1, $this->database->table->count());

        $this->database->rollback();

        $this->assertSame(0, $this->database->table->count());
    }

    public function testCommitTransactionNestedInsert()
    {
        $this->database->begin();

        $this->database->table->insertOne(['name' => 'Anton', 'value' => 123]);
        $this->assertSame(1, $this->database->table->count());

        //Nested savepoint
        $this->database->begin();

        $this->database->table->insertOne(['name' => 'John', 'value' => 456]);
        $this->assertSame(2, $this->database->table->count());

        $this->database->commit();
        $this->assertSame(2, $this->database->table->count());

        $this->database->commit();
        $this->assertSame(2, $this->database->table->count());
    }

    public function testRollbackTransactionNestedInsert()
    {
        $this->database->begin();

        $this->database->table->insertOne(['name' => 'Anton', 'value' => 123]);
        $this->assertSame(1, $this->database->table->count());

        //Nested savepoint
        $this->database->begin();

        $this->database->table->insertOne(['name' => 'John', 'value' => 456]);
        $this->assertSame(2, $this->database->table->count());

        $this->database->rollback();
        $this->assertSame(1, $this->database->table->count());

        $this->database->commit();
        $this->assertSame(1, $this->database->table->count());
    }
}