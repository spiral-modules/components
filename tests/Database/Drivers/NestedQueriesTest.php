<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\Database\Drivers;

use Spiral\Database\Builders\SelectQuery;
use Spiral\Database\Entities\Database;
use Spiral\Database\Schemas\Prototypes\AbstractTable;
use Spiral\Pagination\PaginatorAwareInterface;

abstract class NestedQueriesTest extends BaseQueryTest
{
    /**
     * @var Database
     */
    protected $database;

    public function setUp()
    {
        $this->database = $this->database();
    }

    public function schema(string $table): AbstractTable
    {
        return $this->database->table($table)->getSchema();
    }

    public function testQueryInstance()
    {
        $this->assertInstanceOf(SelectQuery::class, $this->database->select());
        $this->assertInstanceOf(SelectQuery::class, $this->database->table('table')->select());
        $this->assertInstanceOf(SelectQuery::class, $this->database->table->select());
        $this->assertInstanceOf(\IteratorAggregate::class, $this->database->table->select());
        $this->assertInstanceOf(PaginatorAwareInterface::class, $this->database->table->select());
    }

    //Generic behaviours

    public function testSimpleSelection()
    {
        $select = $this->database->select()->from('table');

        $this->assertSameQuery("SELECT * FROM {table}", $select);
    }

}