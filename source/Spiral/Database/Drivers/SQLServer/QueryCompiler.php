<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Drivers\SQLServer;

use Psr\Log\LoggerAwareInterface;
use Spiral\Database\Entities\QueryCompiler as AbstractCompiler;
use Spiral\Database\Injections\SQLFragment;
use Spiral\Debug\Traits\LoggerTrait;

/**
 * Microsoft SQL server specific syntax compiler.
 */
class QueryCompiler extends AbstractCompiler implements LoggerAwareInterface
{
    /**
     * There is few warning notices.
     */
    use LoggerTrait;

    /**
     * @var SQLServerDriver
     */
    protected $driver = null;

    /**
     * @param SQLServerDriver $driver
     * @param string          $tablePrefix
     */
    public function __construct(SQLServerDriver $driver, $tablePrefix = '')
    {
        parent::__construct($driver, $tablePrefix);
    }

    /**
     * {@inheritdoc}
     */
    public function update($table, array $columns, array $joins = [], array $where = [])
    {
        $alias = $table;
        if (preg_match('/ as /i', $alias, $matches))
        {
            list(, $alias) = explode($matches[0], $table);
        }
        else
        {
            $table = "{$table} AS {$table}";
        }

        //This is required to prepare alias
        $table = $this->quote($table, true, true);

        $statement = "UPDATE " . $this->quote($alias);

        //We have to compile JOINs first
        $joinsStatement = '';
        if (!empty($joins))
        {
            $joinsStatement = $this->joins($joins);
        }

        $statement .= "\nSET" . $this->prepareColumns($columns) . "\nFROM " . $table . $joinsStatement;

        if (!empty($where))
        {
            $statement .= "\nWHERE " . $this->where($where);
        }

        return rtrim($statement);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($table, array $joins = [], array $where = [])
    {
        $alias = $table;
        if (preg_match('/ as /i', $alias, $matches))
        {
            list(, $alias) = explode($matches[0], $table);
        }
        else
        {
            $alias = $this->tablePrefix . $alias;
        }

        $statement = "DELETE " . $this->quote($alias) . " FROM " . $this->quote($table, true);

        if (!empty($joins))
        {
            $statement .= $this->joins($joins) . ' ';
        }

        if (!empty($where))
        {
            $statement .= "\nWHERE " . $this->where($where);
        }

        return rtrim($statement);
    }


    /**
     * {@inheritdoc}
     *
     * Attention, limiting and ordering UNIONS will fail in SQL SERVER < 2012.
     * For future upgrades: think about using top command.
     *
     * @link http://stackoverflow.com/questions/603724/how-to-implement-limit-with-microsoft-sql-server
     * @link http://stackoverflow.com/questions/971964/limit-10-20-in-sql-server
     */
    public function select(
        array $from,
        $distinct,
        array $columns,
        array $joins = [],
        array $where = [],
        array $having = [],
        array $groupBy = [],
        array $orderBy = [],
        $limit = 0,
        $offset = 0,
        array $unions = []
    )
    {
        if (
            empty($limit) && empty($offset)
            || ($this->driver->getServerVersion() >= 12 && !empty($orderBy))
        )
        {
            return call_user_func_array(['parent', 'select'], func_get_args());
        }

        if ($this->driver->getServerVersion() >= 12)
        {
            $this->logger()->warning(
                "You can't use query limiting without specifying ORDER BY statement, sql fallback used."
            );
        }
        else
        {
            $this->logger()->warning(
                "You are using older version of SQLServer, "
                . "it has some limitation with query limiting and unions."
            );
        }

        if ($orderBy)
        {
            $orderBy = 'ORDER BY ' . $this->orderBy($orderBy);
        }
        else
        {
            $orderBy = "ORDER BY (SELECT NULL)";
        }

        //Will be removed by QueryResult
        $columns[] = new SQLFragment(
            "ROW_NUMBER() OVER ($orderBy) AS " . $this->quote(QueryResult::ROW_NUMBER_COLUMN)
        );

        $selection = parent::select(
            $from,
            $distinct,
            $columns,
            $joins,
            $where,
            $having,
            $groupBy,
            [],
            0,
            0,
            $unions
        );

        return "SELECT * FROM (\n{$selection}\n) AS [selection_alias] "
        . $this->limit($limit, $offset, QueryResult::ROW_NUMBER_COLUMN);
    }

    /**
     * {@inheritdoc}
     *
     * @link http://stackoverflow.com/questions/2135418/equivalent-of-limit-and-offset-for-sql-server
     */
    protected function limit($limit, $offset, $rowNumber = null)
    {
        if (!$rowNumber && $this->driver->getServerVersion() >= 12)
        {
            $statement = "OFFSET {$offset} ROWS ";

            if ($limit)
            {
                $statement .= "FETCH NEXT {$limit} ROWS ONLY";
            }

            return trim($statement);
        }

        $statement = "WHERE {$this->quote($rowNumber)} ";

        //0 = row_number(1)
        $offset = $offset + 1;

        if ($limit)
        {
            $statement .= "BETWEEN {$offset} AND " . ($offset + $limit - 1);
        }
        else
        {
            $statement .= ">= {$offset}";
        }

        return $statement;
    }
}