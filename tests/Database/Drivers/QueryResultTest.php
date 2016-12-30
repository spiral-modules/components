<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\Database\Drivers;

use Spiral\Database\Entities\Database;
use Spiral\Database\Entities\QueryResult;
use Spiral\Database\Schemas\Prototypes\AbstractTable;
use Spiral\Pagination\Paginator;

abstract class QueryResultTest extends BaseQueryTest
{
    const PROFILING = true;
    /**
     * @var Database
     */
    protected $database;

    public function setUp()
    {
        $this->database = $this->database();

        $schema = $this->database->table('table')->getSchema();
        $schema->primary('id');
        $schema->string('name', 64);
        $schema->integer('value');
        $schema->save();
    }

    public function schema(string $table): AbstractTable
    {
        return $this->database->table($table)->getSchema();
    }

    public function fillData()
    {
        $table = $this->database->table('table');

        for ($i = 0; $i < 10; $i++) {
            $table->insertOne([
                'name'  => md5($i),
                'value' => $i * 10
            ]);
        }
    }

    public function tearDown()
    {
        $this->dropAll($this->database);
    }

    public function testInstance()
    {
        $table = $this->database->table('table');

        $this->assertInstanceOf(QueryResult::class, $table->select()->getIterator());
        $this->assertInstanceOf(\PDOStatement::class, $table->select()->getIterator());
    }

    //We are testing only extended functionality, there is no need to test PDOStatement

    public function testCountColumns()
    {
        $table = $this->database->table('table');
        $result = $table->select()->getIterator();

        $this->assertSame(3, $result->countColumns());
    }

    public function testIterateOver()
    {
        $table = $this->database->table('table');
        $this->fillData();

        $result = $table->select()->getIterator();

        $i = 0;
        foreach ($result as $item) {
            $this->assertEquals(md5($i), $item['name']);
            $this->assertEquals($i * 10, $item['value']);

            $i++;
        }

        $this->assertSameQuery(
            'SELECT * FROM {table}',
            $result->queryString()
        );

        $this->assertSame(10, $i);
    }

    public function testIterateOverLimit()
    {
        $table = $this->database->table('table');
        $this->fillData();

        $result = $table->select()->limit(5)->getIterator();

        $i = 0;
        foreach ($result as $item) {
            $this->assertEquals(md5($i), $item['name']);
            $this->assertEquals($i * 10, $item['value']);

            $i++;
        }

        $this->assertSame(5, $i);
    }

    public function testIterateOverOffset()
    {
        $table = $this->database->table('table');
        $this->fillData();

        $result = $table->select()->offset(5)->getIterator();

        $i = 5;
        foreach ($result as $item) {
            $this->assertEquals(md5($i), $item['name']);
            $this->assertEquals($i * 10, $item['value']);

            $i++;
        }

        $this->assertSame(10, $i);
    }

    public function testIterateOverOffsetAndLimit()
    {
        $table = $this->database->table('table');
        $this->fillData();

        $result = $table->select()->offset(5)->limit(2)->getIterator();

        $i = 5;
        foreach ($result as $item) {
            $this->assertEquals(md5($i), $item['name']);
            $this->assertEquals($i * 10, $item['value']);

            $i++;
        }

        $this->assertSame(7, $i);
    }

    public function testPaginate()
    {
        $table = $this->database->table('table');
        $this->fillData();

        $paginator = new Paginator(2);

        $select = $table->select();

        $select->setPaginator($paginator->withPage(1));

        $i = 0;
        foreach ($select as $item) {
            $this->assertEquals(md5($i), $item['name']);
            $this->assertEquals($i * 10, $item['value']);

            $i++;
        }

        $this->assertSame(2, $i);

        $select->setPaginator($paginator->withPage(2));

        $i = 2;
        foreach ($select as $item) {
            $this->assertEquals(md5($i), $item['name']);
            $this->assertEquals($i * 10, $item['value']);

            $i++;
        }

        $this->assertSame(4, $i);

        $select->setPaginator($paginator->withPage(3));

        $i = 4;
        foreach ($select as $item) {
            $this->assertEquals(md5($i), $item['name']);
            $this->assertEquals($i * 10, $item['value']);

            $i++;
        }

        $this->assertSame(6, $i);

        $paginator = $paginator->withLimit(6);
        $select->setPaginator($paginator->withPage(4)); //Forced last page

        $i = 6;
        foreach ($select as $item) {
            $this->assertEquals(md5($i), $item['name']);
            $this->assertEquals($i * 10, $item['value']);

            $i++;
        }

        $this->assertSame(10, $i);
    }

    public function testDebugString()
    {
        $table = $this->database->table('table');
        $result = $table->select()->getIterator();

        $this->assertSameQuery(
            'SELECT * FROM {table}',
            $result->queryString()
        );
    }

    public function testToArray()
    {
        $table = $this->database->table('table');
        $this->fillData();

        $result = $table->select()->limit(1)->getIterator();

        $this->assertEquals([
            ['id' => 1, 'name' => md5(0), 'value' => 0]
        ], $result->toArray());
    }

    public function testClone()
    {
        $table = $this->database->table('table');
        $this->fillData();
        $result = $table->select()->getIterator();

        $result->close();
    }

    public function testBindByName()
    {
        $table = $this->database->table('table');
        $this->fillData();

        $result = $table->select()->getIterator();

        $result->bind('name', $name);

        foreach ($result as $item) {
            $this->assertSame($name, $item['name']);
        }
    }

    public function testBindByNumber()
    {
        $table = $this->database->table('table');
        $this->fillData();

        $result = $table->select()->getIterator();

        //Id is = 0
        $result->bind(1, $name);

        foreach ($result as $item) {
            $this->assertSame($name, $item['name']);
        }
    }
}