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

abstract class DatetimeColumnsTest extends AbstractTest
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
        $schema = $this->schema($table);

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
            $schema->datetime('datetime')->defaultValue('2017-01-01 00:00:00');
            $schema->date('datetime')->nullable(true);
            $schema->time('datetime')->defaultValue('00:00');

            $schema->save(AbstractHandler::DO_ALL);
        }

        return $schema;
    }

    public function testTimestampWithNullDefaultAndNullable()
    {
        $schema = $this->schema('sampleSchema');
        $this->assertFalse($schema->exists());

        $schema->timestamp('timestamp')->nullable(true)->defaultValue(null);
        $schema->save();

        $this->assertSameAsInDB($schema);
    }

    public function testTimestampCurrentTimestamp()
    {
        $schema = $this->schema('sampleSchema');
        $this->assertFalse($schema->exists());

        $schema->timestamp('timestamp')->defaultValue(AbstractColumn::DATETIME_NOW);
        $schema->save();

        $this->assertSameAsInDB($schema);
    }

    public function testMultipleTimestampCurrentTimestamp()
    {
        $schema = $this->schema('sampleSchema');
        $this->assertFalse($schema->exists());

        $schema->timestamp('timestamp')->defaultValue(AbstractColumn::DATETIME_NOW);
        $schema->timestamp('timestamp2')->defaultValue(AbstractColumn::DATETIME_NOW);
        $schema->save();

        $this->assertSameAsInDB($schema);
    }

//    public function testTimestampDatetime()
//    {
//        $schema = $this->schema('sampleSchema');
//        $this->assertFalse($schema->exists());
//
//        $schema->timestamp('target')->defaultValue(new \DateTime('1980-01-01 19:00:00'));
//        $schema->save();
//
//        $savedSchema = $this->schema('sampleSchema');
//        $this->assertSame(
//            $schema->column('target')->getDefaultValue()->getTimestamp(),
//            $savedSchema->column('target')->getDefaultValue()->getTimestamp()
//        );
//    }

//    public function testTimestampDatetimeString()
//    {
//        $schema = $this->schema('sampleSchema');
//        $this->assertFalse($schema->exists());
//
//        $schema->timestamp('target')->defaultValue('1980-01-01 19:00:00');
//        $schema->save();
//
//        $savedSchema = $this->schema('sampleSchema');
//        $this->assertSame(
//            $schema->column('target')->getDefaultValue()->getTimestamp(),
//            $savedSchema->column('target')->getDefaultValue()->getTimestamp()
//        );
//    }

//    public function testTimestampDatetimeZero()
//    {
//        $schema = $this->schema('sampleSchema');
//        $this->assertFalse($schema->exists());
//
//        $schema->timestamp('target')->defaultValue(0);
//        $schema->save();
//
//        $savedSchema = $this->schema('sampleSchema');
//        $this->assertSame(
//            $schema->column('target')->getDefaultValue()->getTimestamp(),
//            $savedSchema->column('target')->getDefaultValue()->getTimestamp()
//        );
//    }

    protected function assertSameDate(\DateTime $a, \DateTime $b)
    {

    }
}