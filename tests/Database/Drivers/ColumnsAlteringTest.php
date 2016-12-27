<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\Database\Drivers;

use Spiral\Database\Entities\AbstractHandler;
use Spiral\Database\Entities\Database;
use Spiral\Database\Schemas\Prototypes\AbstractColumn;
use Spiral\Database\Schemas\Prototypes\AbstractTable;
use Spiral\Database\Schemas\StateComparator;

abstract class ColumnsAlteringTest extends AbstractTest
{
    /**
     * @var Database
     */
    protected $database;

    public function setUp()
    {
        $this->database = $this->database();
    }

    public function tearDown()
    {
        $this->dropAll($this->database());
    }

    public function schema(string $table): AbstractTable
    {
        return $this->database->table($table)->getSchema();
    }

    protected function sampleSchema(string $table): AbstractTable
    {
        $schema = $this->database->table($table)->getSchema();

        if (!$schema->exists()) {
            $schema->primary('id');
            $schema->string('first_name')->nullable(false);
            $schema->string('last_name')->nullable(false);
            $schema->string('email', 64)->nullable(false);
            $schema->enum('status', ['active', 'disabled'])->defaultValue('active');
            $schema->double('balance')->defaultValue(0);
            $schema->boolean('flagged')->defaultValue(true);

            $schema->text('bio');

            //Some dates
            $schema->timestamp('timestamp')->defaultValue(AbstractColumn::DATETIME_NOW);
            $schema->datetime('datetime')->datetime('2017-01-01 00:00:00');
            $schema->date('datetime')->nullable(true);
            $schema->time('datetime')->defaultValue('00:00');

            $schema->save(AbstractHandler::DO_ALL);
        }

        return $schema;
    }

    //Verification test #1
    public function testSelfCompareNew()
    {
        $schema = $this->schema('table');
        $this->assertFalse($schema->exists());

        $this->assertProperlySaved($schema);
    }

    //Verification test #2
    public function testSelfComparePreparedSameInstance()
    {
        $schema = $this->sampleSchema('table');
        $this->assertTrue($schema->exists());

        $this->assertProperlySaved($schema);
    }

    //Verification test #3
    public function testSelfComparePreparedReselected()
    {
        $schema = $this->sampleSchema('table');
        $this->assertTrue($schema->exists());

        $schema = $this->schema('table');
        $this->assertTrue($schema->exists());

        $this->assertProperlySaved($schema);
    }

    public function testAddColumn()
    {
        $schema = $this->sampleSchema('table');
        $this->assertTrue($schema->exists());

        $schema->string('new_column');
        $schema->save();

        $this->assertProperlySaved($schema);
    }

    public function testAddColumnWithDefaultValue()
    {
        $schema = $this->sampleSchema('table');
        $this->assertTrue($schema->exists());

        $schema->string('new_column')->defaultValue('some_value');
        $schema->save();

        $this->assertProperlySaved($schema);
    }

    public function testAddColumnNullable()
    {
        $schema = $this->sampleSchema('table');
        $this->assertTrue($schema->exists());

        $schema->string('new_column')->nullable(true);
        $schema->save();

        $this->assertProperlySaved($schema);
    }

    public function testAddColumnNotNullable()
    {
        $schema = $this->sampleSchema('table');
        $this->assertTrue($schema->exists());

        $schema->string('new_column')->nullable(false);
        $schema->save();

        $this->assertProperlySaved($schema);
    }

    public function testAddColumnEnum()
    {
        $schema = $this->sampleSchema('table');
        $this->assertTrue($schema->exists());

        $schema->enum('new_column', ['a', 'b', 'c'])->nullable('a');
        $schema->save();

        $this->assertProperlySaved($schema);
    }

    public function testAddColumnEnumNullDefault()
    {
        $schema = $this->sampleSchema('table');
        $this->assertTrue($schema->exists());

        $schema->enum('new_column', ['a', 'b', 'c'])->defaultValue(null);
        $schema->save();

        $this->assertProperlySaved($schema);
    }

    public function testAddMultipleColumns()
    {
        $schema = $this->sampleSchema('table');
        $this->assertTrue($schema->exists());

        $schema->integer('new_int')->defaultValue(0);
        $schema->integer('new_string_0_default')->defaultValue(0);
        $schema->enum('new_column', ['a', 'b', 'c'])->nullable('a');
        $schema->save();

        $this->assertProperlySaved($schema);
    }

    public function testDropColumn()
    {
        $schema = $this->sampleSchema('table');
        $this->assertTrue($schema->exists());

        $schema->dropColumn('first_name');
        $schema->save();

        $this->assertProperlySaved($schema);
    }

    public function testDropMultipleColumns()
    {
        $schema = $this->sampleSchema('table');
        $this->assertTrue($schema->exists());

        $schema->dropColumn('first_name');
        $schema->dropColumn('last_name');
        $schema->save();

        $this->assertProperlySaved($schema);
    }

    public function testRenameColumn()
    {
        $schema = $this->sampleSchema('table');
        $this->assertTrue($schema->exists());

        $schema->renameColumn('first_name', 'another_name');
        $schema->save();

        $this->assertProperlySaved($schema);
    }

    public function testRenameMultipleColumns()
    {
        $schema = $this->sampleSchema('table');
        $this->assertTrue($schema->exists());

        $schema->renameColumn('first_name', 'another_name');

        //I have no idea what will happen at moment i write this comment
        $schema->renameColumn('last_name', 'first_name');
        //it worked O_o

        $schema->save();

        $this->assertProperlySaved($schema);
    }

    public function testChangeColumnFromNullToNotNull()
    {
        $schema = $this->sampleSchema('table');
        $this->assertTrue($schema->exists());

        $schema->column('first_name')->nullable(false);

        $schema->save();

        $this->assertProperlySaved($schema);
    }

    public function testChangeColumnFromNotNullToNull()
    {
        $schema = $this->sampleSchema('table');
        $this->assertTrue($schema->exists());

        $schema->column('flagged')->nullable(true);

        $schema->save();

        $this->assertProperlySaved($schema);
    }

    public function testRenameAndDropColumn()
    {
        $schema = $this->sampleSchema('table');
        $this->assertTrue($schema->exists());

        $schema->renameColumn('first_name', 'name');
        $schema->dropColumn('last_name');
        $schema->save();

        $this->assertProperlySaved($schema);
    }

    public function testRenameAndChangeToNotNull()
    {
        $schema = $this->sampleSchema('table');
        $this->assertTrue($schema->exists());

        $schema->renameColumn('first_name', 'name');
        $schema->column('name')->nullable(true);

        $schema->save();

        $this->assertProperlySaved($schema);
    }

    public function testRenameAndChangeToNullAndSetNulL()
    {
        $schema = $this->sampleSchema('table');
        $this->assertTrue($schema->exists());

        $schema->renameColumn('flagged', 'name');
        $schema->column('name')->nullable(true);

        $schema->save();

        $this->assertProperlySaved($schema);
    }

    public function testRenameAndChangeToNullAndSetNullDefaultValue()
    {
        $schema = $this->sampleSchema('table');
        $this->assertTrue($schema->exists());

        $schema->renameColumn('flagged', 'name');
        $schema->column('name')->nullable(true)->defaultValue(null);

        $schema->save();

        $this->assertProperlySaved($schema);
    }

    /**
     * @param AbstractTable $current
     */
    protected function assertProperlySaved(AbstractTable $current)
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
            return "Table '{$table}' not synced, columns missing.";
        }

        if ($comparator->addedColumns()) {
            return "Table '{$table}' not synced, new columns found.";
        }

        if ($comparator->alteredColumns()) {
            return "Table '{$table}' not synced, columns not identical.";
        }

        return "Table '{$table}' not synced, no idea why, add more messages :P";
    }
}