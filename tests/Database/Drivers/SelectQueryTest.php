<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\Database\Drivers;

use Spiral\Database\Builders\SelectQuery;
use Spiral\Database\Entities\Database;
use Spiral\Database\Injections\Parameter;
use Spiral\Database\Schemas\Prototypes\AbstractTable;

abstract class SelectQueryTest extends BaseQueryTest
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
    }

    //Generic behaviours

    public function testSimpleSelection()
    {
        $select = $this->database->select()->from('table');

        $this->assertSameQuery("SELECT * FROM {table}", $select);
    }

    public function testMultipleTablesSelection()
    {
        $select = $this->database->select()->from(['tableA', 'tableB']);

        $this->assertSameQuery("SELECT * FROM {tableA}, {tableB}", $select);
    }

    public function testSelectDistinct()
    {
        $select = $this->database->select()->distinct()->from(['table']);

        $this->assertSameQuery("SELECT DISTINCT * FROM {table}", $select);
    }

    public function testSelectWithSimpleWhere()
    {
        $select = $this->database->select()->distinct()->from(['users'])->where('name', 'Anton');

        $this->assertSameQuery(
            "SELECT DISTINCT * FROM {users} WHERE {name} = ?",
            $select
        );
    }

    public function testSelectWithWhereWithOperator()
    {
        $select = $this->database->select()->distinct()->from(['users'])
            ->where('name', 'LIKE', 'Anton%');

        $this->assertSameQuery(
            "SELECT DISTINCT * FROM {users} WHERE {name} LIKE ?",
            $select
        );
    }

    public function testSelectWithWhereWithBetween()
    {
        $select = $this->database->select()->distinct()->from(['users'])
            ->where('balance', 'BETWEEN', 0, 1000);

        $this->assertSameQuery(
            "SELECT DISTINCT * FROM {users} WHERE {balance} BETWEEN ? AND ?",
            $select
        );
    }

    public function testSelectWithWhereWithNotBetween()
    {
        $select = $this->database->select()->distinct()->from(['users'])
            ->where('balance', 'NOT BETWEEN', 0, 1000);

        $this->assertSameQuery(
            "SELECT DISTINCT * FROM {users} WHERE {balance} NOT BETWEEN ? AND ?",
            $select
        );
    }

    public function testSelectWithFullySpecificColumnNameInWhere()
    {
        $select = $this->database->select()->distinct()->from(['users'])
            ->where('users.balance', 12);

        $this->assertSameQuery(
            "SELECT DISTINCT * FROM {users} WHERE {users}.{balance} = ?",
            $select
        );
    }

    public function testPrefixedSelectWithFullySpecificColumnNameInWhere()
    {
        $select = $this->database('prefixed', 'prefix_')->select()->distinct()->from(['users'])
            ->where('users.balance', 12);

        $this->assertSameQuery(
            "SELECT DISTINCT * FROM {prefix_users} WHERE {prefix_users}.{balance} = ?",
            $select
        );
    }

    public function testPrefixedSelectWithFullySpecificColumnNameInWhereButAliased()
    {
        $select = $this->database('prefixed', 'prefix_')->select()->distinct()->from(['users as u'])
            ->where('u.balance', 12);

        $this->assertSameQuery(
            "SELECT DISTINCT * FROM {prefix_users} AS {u} WHERE {u}.{balance} = ?",
            $select
        );
    }

    //Simple combinations testing

    public function testSelectWithWhereAndWhere()
    {
        $select = $this->database->select()->distinct()
            ->from(['users'])
            ->where('name', 'Anton')
            ->andWhere('balance', '>', 1);

        $this->assertSameQuery(
            "SELECT DISTINCT * FROM {users} WHERE {name} = ? AND {balance} > ?",
            $select
        );
    }

    public function testSelectWithWhereAndFallbackWhere()
    {
        $select = $this->database->select()->distinct()
            ->from(['users'])
            ->where('name', 'Anton')
            ->where('balance', '>', 1);

        $this->assertSameQuery(
            "SELECT DISTINCT * FROM {users} WHERE {name} = ? AND {balance} > ?",
            $select
        );
    }

    public function testSelectWithWhereOrWhere()
    {
        $select = $this->database->select()->distinct()
            ->from(['users'])
            ->where('name', 'Anton')
            ->orWhere('balance', '>', 1);

        $this->assertSameQuery(
            "SELECT DISTINCT * FROM {users} WHERE {name} = ? OR {balance} > ?",
            $select
        );
    }

    public function testSelectWithWhereOrWhereAndWhere()
    {
        $select = $this->database->select()->distinct()
            ->from(['users'])
            ->where('name', 'Anton')
            ->orWhere('balance', '>', 1)
            ->andWhere('value', 'IN', new Parameter([10, 12]));

        $this->assertSameQuery(
            "SELECT DISTINCT * FROM {users} WHERE {name} = ? OR {balance} > ? AND {value} IN (?, ?)",
            $select
        );
    }

    //Combinations thought closures

    public function testWhereOfOrWhere()
    {
        $select = $this->database->select()
            ->from(['users'])
            ->where('name', 'Anton')
            ->andWhere(function (SelectQuery $select) {
                $select->orWhere('value', '>', 10)->orWhere('value', '<', 1000);
            });

        $this->assertSameQuery(
            "SELECT * FROM {users} WHERE {name} = ? AND ({value} > ? OR {value} < ?)",
            $select
        );
    }

    public function testWhereOfAndWhere()
    {
        $select = $this->database->select()
            ->from(['users'])
            ->where('name', 'Anton')
            ->andWhere(function (SelectQuery $select) {
                $select->where('value', '>', 10)->andWhere('value', '<', 1000);
            });

        $this->assertSameQuery(
            "SELECT * FROM {users} WHERE {name} = ? AND ({value} > ? AND {value} < ?)",
            $select
        );
    }

    public function testOrWhereOfOrWhere()
    {
        $select = $this->database->select()
            ->from(['users'])
            ->where('name', 'Anton')
            ->orWhere(function (SelectQuery $select) {
                $select->orWhere('value', '>', 10)->orWhere('value', '<', 1000);
            });

        $this->assertSameQuery(
            "SELECT * FROM {users} WHERE {name} = ? OR ({value} > ? OR {value} < ?)",
            $select
        );
    }

    public function testOrWhereOfAndWhere()
    {
        $select = $this->database->select()
            ->from(['users'])
            ->where('name', 'Anton')
            ->orWhere(function (SelectQuery $select) {
                $select->where('value', '>', 10)->andWhere('value', '<', 1000);
            });

        $this->assertSameQuery(
            "SELECT * FROM {users} WHERE {name} = ? OR ({value} > ? AND {value} < ?)",
            $select
        );
    }

    //Short where form

    public function testShortWhere()
    {
        $select = $this->database->select()
            ->from(['users'])
            ->where(['name' => 'Anton']);

        $this->assertSameQuery(
            "SELECT * FROM {users} WHERE {name} = ?",
            $select
        );
    }

    public function testShortWhereWithCondition()
    {
        $select = $this->database->select()
            ->from(['users'])
            ->where([
                'name' => [
                    'like' => 'Anton',
                    '!='   => 'Antony'
                ]
            ]);

        $this->assertSameQuery(
            "SELECT * FROM {users} WHERE ({name} LIKE ? AND {name} != ?)",
            $select
        );
    }

    public function testShortWhereMultiple()
    {
        $select = $this->database->select()
            ->from(['users'])
            ->where([
                'name'  => 'Anton',
                'value' => 1
            ]);

        $this->assertSameQuery(
            "SELECT * FROM {users} WHERE ({name} = ? AND {value} = ?)",
            $select
        );
    }

    public function testShortWhereMultipleButNotInAGroup()
    {
        $select = $this->database->select()
            ->from(['users'])
            ->where(['name' => 'Anton'])
            ->where(['value' => 1]);

        $this->assertSameQuery(
            "SELECT * FROM {users} WHERE {name} = ? AND {value} = ?",
            $select
        );
    }

    public function testShortWhereOrWhere()
    {
        $select = $this->database->select()
            ->from(['users'])
            ->where(['name' => 'Anton'])
            ->orWhere(['value' => 1]);

        $this->assertSameQuery(
            "SELECT * FROM {users} WHERE {name} = ? OR {value} = ?",
            $select
        );
    }

    public function testAndShortWhereOR()
    {
        $select = $this->database->select()
            ->from(['users'])
            ->where(['name' => 'Anton'])
            ->andWhere([
                '@or' => [
                    ['value' => 1],
                    ['value' => ['>' => 12]]
                ]
            ]);

        $this->assertSameQuery(
            "SELECT * FROM {users} WHERE {name} = ? AND ({value} = ? OR {value} > ?)",
            $select
        );
    }

    public function testOrShortWhereOR()
    {
        $select = $this->database->select()
            ->from(['users'])
            ->where(['name' => 'Anton'])
            ->orWhere([
                '@or' => [
                    ['value' => 1],
                    ['value' => ['>' => 12]]
                ]
            ]);

        $this->assertSameQuery(
            "SELECT * FROM {users} WHERE {name} = ? OR ({value} = ? OR {value} > ?)",
            $select
        );
    }

    public function testAndShortWhereAND()
    {
        $select = $this->database->select()
            ->from(['users'])
            ->where(['name' => 'Anton'])
            ->andWhere([
                '@and' => [
                    ['value' => 1],
                    ['value' => ['>' => 12]]
                ]
            ]);

        $this->assertSameQuery(
            "SELECT * FROM {users} WHERE {name} = ? AND ({value} = ? AND {value} > ?)",
            $select
        );
    }

    public function testOrShortWhereAND()
    {
        $select = $this->database->select()
            ->from(['users'])
            ->where(['name' => 'Anton'])
            ->orWhere([
                '@and' => [
                    ['value' => 1],
                    ['value' => ['>' => 12]]
                ]
            ]);

        $this->assertSameQuery(
            "SELECT * FROM {users} WHERE {name} = ? OR ({value} = ? AND {value} > ?)",
            $select
        );
    }

    //Order By

    public function testOrderByAsc()
    {
        $select = $this->database->select()
            ->from(['users'])
            ->where(['name' => 'Anton'])
            ->orderBy('name');

        $this->assertSameQuery(
            "SELECT * FROM {users} WHERE {name} = ? ORDER BY {name} ASC",
            $select
        );
    }

    public function testOrderByAsc2()
    {
        $select = $this->database->select()
            ->from(['users'])
            ->where(['name' => 'Anton'])
            ->orderBy('name', SelectQuery::SORT_ASC);

        $this->assertSameQuery(
            "SELECT * FROM {users} WHERE {name} = ? ORDER BY {name} ASC",
            $select
        );
    }

    public function testOrderByAsc3()
    {
        $select = $this->database->select()
            ->from(['users'])
            ->where(['name' => 'Anton'])
            ->orderBy('name', 'ASC');

        $this->assertSameQuery(
            "SELECT * FROM {users} WHERE {name} = ? ORDER BY {name} ASC",
            $select
        );
    }

    public function testOrderByDesc()
    {
        $select = $this->database->select()
            ->from(['users'])
            ->where(['name' => 'Anton'])
            ->orderBy('name', SelectQuery::SORT_DESC);

        $this->assertSameQuery(
            "SELECT * FROM {users} WHERE {name} = ? ORDER BY {name} DESC",
            $select
        );
    }

    public function testOrderByDesc3()
    {
        $select = $this->database->select()
            ->from(['users'])
            ->where(['name' => 'Anton'])
            ->orderBy('name', 'DESC');

        $this->assertSameQuery(
            "SELECT * FROM {users} WHERE {name} = ? ORDER BY {name} DESC",
            $select
        );
    }

    public function testMultipleOrderBy()
    {
        $select = $this->database->select()
            ->from(['users'])
            ->where(['name' => 'Anton'])
            ->orderBy('value', SelectQuery::SORT_ASC)
            ->orderBy('name', SelectQuery::SORT_DESC);

        $this->assertSameQuery(
            "SELECT * FROM {users} WHERE {name} = ? ORDER BY {value} ASC, {name} DESC",
            $select
        );
    }

    public function testMultipleOrderByFullySpecified()
    {
        $select = $this->database->select()
            ->from(['users'])
            ->where(['name' => 'Anton'])
            ->orderBy('users.value', SelectQuery::SORT_ASC);

        $this->assertSameQuery(
            "SELECT * FROM {users} WHERE {name} = ? ORDER BY {users}.{value} ASC",
            $select
        );
    }

    public function testMultipleOrderByFullySpecifiedPrefixed()
    {
        $select = $this->database('prefixed', 'prefix_')->select()
            ->from(['users'])
            ->where(['name' => 'Anton'])
            ->orderBy('users.value', SelectQuery::SORT_ASC);

        $this->assertSameQuery(
            "SELECT * FROM {prefix_users} WHERE {name} = ? ORDER BY {prefix_users}.{value} ASC",
            $select
        );
    }

    public function testMultipleOrderByFullySpecifiedAliasedAndPrefixed()
    {
        $select = $this->database('prefixed', 'prefix_')->select()
            ->from(['users as u'])
            ->where(['name' => 'Anton'])
            ->orderBy('u.value', SelectQuery::SORT_ASC);

        $this->assertSameQuery(
            "SELECT * FROM {prefix_users} AS {u} WHERE {name} = ? ORDER BY {u}.{value} ASC",
            $select
        );
    }

    //Group By

    public function testGroupBy()
    {
        $select = $this->database->select()
            ->from(['users'])
            ->where(['name' => 'Anton'])
            ->groupBy('name');

        $this->assertSameQuery(
            "SELECT * FROM {users} WHERE {name} = ? GROUP BY {name}",
            $select
        );
    }

    public function testMultipleGroupByFullySpecified()
    {
        $select = $this->database->select()
            ->from(['users'])
            ->where(['name' => 'Anton'])
            ->groupBy('users.value');

        $this->assertSameQuery(
            "SELECT * FROM {users} WHERE {name} = ? GROUP BY {users}.{value}",
            $select
        );
    }

    public function testMultipleGroupByFullySpecifiedPrefixed()
    {
        $select = $this->database('prefixed', 'prefix_')->select()
            ->from(['users'])
            ->where(['name' => 'Anton'])
            ->groupBy('users.value');

        $this->assertSameQuery(
            "SELECT * FROM {prefix_users} WHERE {name} = ? GROUP BY {prefix_users}.{value}",
            $select
        );
    }

    public function testMultipleGroupByFullySpecifiedAliasedAndPrefixed()
    {
        $select = $this->database('prefixed', 'prefix_')->select()
            ->from(['users as u'])
            ->where(['name' => 'Anton'])
            ->groupBy('u.value');

        $this->assertSameQuery(
            "SELECT * FROM {prefix_users} AS {u} WHERE {name} = ? GROUP BY {u}.{value}",
            $select
        );
    }

    //Column Tweaking

    public function testAllColumns()
    {
        $select = $this->database->select()
            ->from(['users'])
            ->where(['name' => 'Anton']);

        $this->assertSameQuery(
            "SELECT * FROM {users} WHERE {name} = ?",
            $select
        );
    }

    public function testAllColumns1()
    {
        $select = $this->database->select('*')
            ->from(['users'])
            ->where(['name' => 'Anton']);

        $this->assertSameQuery(
            "SELECT * FROM {users} WHERE {name} = ?",
            $select
        );
    }

    public function testAllColumns2()
    {
        $select = $this->database->select(['*'])
            ->from(['users'])
            ->where(['name' => 'Anton']);

        $this->assertSameQuery(
            "SELECT * FROM {users} WHERE {name} = ?",
            $select
        );
    }

    public function testAllColumns3()
    {
        $select = $this->database->select([])
            ->from(['users'])
            ->where(['name' => 'Anton']);

        $this->assertSameQuery(
            "SELECT * FROM {users} WHERE {name} = ?",
            $select
        );
    }

    public function testAllColumns4()
    {
        $select = $this->database->select()
            ->columns('*')
            ->from(['users'])
            ->where(['name' => 'Anton']);

        $this->assertSameQuery(
            "SELECT * FROM {users} WHERE {name} = ?",
            $select
        );
    }

    public function testAllColumns5()
    {
        $select = $this->database->select()
            ->columns('users.*')
            ->from(['users'])
            ->where(['name' => 'Anton']);

        $this->assertSameQuery(
            "SELECT {users}.* FROM {users} WHERE {name} = ?",
            $select
        );
    }

    public function testAllColumnsWithPrefix()
    {
        $select = $this->database('prefixed', 'prefix_')->select()
            ->columns('users.*')
            ->from(['users'])
            ->where(['name' => 'Anton']);

        $this->assertSameQuery(
            "SELECT {prefix_users}.* FROM {prefix_users} WHERE {name} = ?",
            $select
        );
    }

    public function testAllColumnsWithPrefixAliased()
    {
        $select = $this->database('prefixed', 'prefix_')->select()
            ->columns('u.*')
            ->from(['users as u'])
            ->where(['name' => 'Anton']);

        $this->assertSameQuery(
            "SELECT {u}.* FROM {prefix_users} AS {u} WHERE {name} = ?",
            $select
        );
    }

    public function testOneColumn()
    {
        $select = $this->database->select()
            ->columns('name')
            ->from(['users'])
            ->where(['name' => 'Anton']);

        $this->assertSameQuery(
            "SELECT {name} FROM {users} WHERE {name} = ?",
            $select
        );
    }

    public function testOneFullySpecifiedColumn()
    {
        $select = $this->database->select()
            ->columns('users.name')
            ->from(['users'])
            ->where(['name' => 'Anton']);

        $this->assertSameQuery(
            "SELECT {users}.{name} FROM {users} WHERE {name} = ?",
            $select
        );
    }

    public function testOneFullySpecifiedColumnWithPrefix()
    {
        $select = $this->database('prefixed', 'prefix_')->select()
            ->columns('users.name')
            ->from(['users'])
            ->where(['name' => 'Anton']);

        $this->assertSameQuery(
            "SELECT {prefix_users}.{name} FROM {prefix_users} WHERE {name} = ?",
            $select
        );
    }

    public function testOneFullySpecifiedColumnWithPrefixButAliased()
    {
        $select = $this->database('prefixed', 'prefix_')->select()
            ->columns('u.name')
            ->from(['users as u'])
            ->where(['name' => 'Anton']);

        $this->assertSameQuery(
            "SELECT {u}.{name} FROM {prefix_users} AS {u} WHERE {name} = ?",
            $select
        );
    }

    public function testColumnWithAlias()
    {
        $select = $this->database('prefixed', 'prefix_')->select()
            ->columns('u.name as u_name')
            ->from(['users as u'])
            ->where(['u_name' => 'Anton']);

        $this->assertSameQuery(
            "SELECT {u}.{name} AS {u_name} FROM {prefix_users} AS {u} WHERE {u_name} = ?",
            $select
        );
    }

    public function testMultipleColumns()
    {
        $select = $this->database->select()
            ->columns(['name', 'value'])
            ->from(['users as u'])
            ->where(['name' => 'Anton']);

        $this->assertSameQuery(
            "SELECT {name}, {value} FROM {users} AS {u} WHERE {name} = ?",
            $select
        );
    }

    //Having


    //Fragments!!!!!

    //Parameters

    //Nested queries

    //having

    //Complex examples
}