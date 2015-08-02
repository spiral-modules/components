<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Entities;

use Spiral\Core\Component;
use Spiral\Database\Exceptions\CompilerException;
use Spiral\Database\Injections\SQLFragmentInterface;
use Spiral\Database\ParameterInterface;
use Spiral\Database\QueryBuilder;

/**
 * Responsible for conversion set of query parameters (where tokens, table names and etc) int sql
 * specific for associated driver.
 */
class QueryCompiler extends Component
{
    /**
     * Query types for parameter ordering.
     */
    const SELECT_QUERY = 'select';
    const UPDATE_QUERY = 'update';
    const DELETE_QUERY = 'delete';
    const INSERT_QUERY = 'insert';

    /**
     * Table prefix will be applied to every table name found in query.
     *
     * @var string
     */
    private $tablePrefix = '';

    /**
     * Cached list of table aliases used to correctly inject prefixed tables into conditions.
     *
     * @var array
     */
    private $aliases = [];

    /**
     * Associated driver instance, may be required for some data assumptions.
     *
     * @var Driver
     */
    protected $driver = null;

    /**
     * @param Driver $driver
     * @param string $tablePrefix
     */
    public function __construct(Driver $driver, $tablePrefix = '')
    {
        $this->driver = $driver;
        $this->tablePrefix = $tablePrefix;
    }

    /**
     * Create insert query using table names, columns and rowsets. Must support both - single and batch
     * inserts.
     *
     * @param string $table
     * @param array  $columns
     * @param array  $rowsets
     * @return string
     * @throws CompilerException
     */
    public function insert($table, array $columns, array $rowsets)
    {
        if (empty($columns))
        {
            throw new CompilerException("Unable to build insert statement, columns must be set.");
        }

        if (empty($rowsets))
        {
            throw new CompilerException(
                "Unable to build insert statement, at least one value set must be provided."
            );
        }

        return "INSERT INTO {$this->quote($table, true)} ({$this->columns($columns)})\n"
        . "VALUES " . join(",\n", $rowsets);
    }


    /**
     * Create update statement. Compiler must mount joins and where conditions.
     *
     * @param string $table
     * @param array  $columns
     * @param array  $joins
     * @param array  $where
     * @return string
     */
    public function update($table, array $columns, array $joins = [], array $where = [])
    {
        $statement = "UPDATE " . $this->quote($table, true, true)
            . "\nSET" . $this->prepareColumns($columns);

        if (!empty($where))
        {
            $statement .= "\nWHERE " . $this->where($where);
        }

        return rtrim($statement);
    }

    /**
     * Create delete statement. Compiler must mount joins and where conditions.
     *
     * @param string $table
     * @param array  $joins
     * @param array  $where
     * @return string
     */
    public function delete($table, array $joins = [], array $where = [])
    {
        $statement = 'DELETE FROM ' . $this->quote($table, true);
        if (!empty($where))
        {
            $statement .= "\nWHERE " . $this->where($where);
        }

        //Joins must be rendered by database specific compiler

        return rtrim($statement);
    }

    /**
     * Create select statement. Compiler must validly resolve table and column aliases used in
     * conditions and joins.
     *
     * @param array   $from
     * @param boolean $distinct
     * @param array   $columns
     * @param array   $joins
     * @param array   $where
     * @param array   $having
     * @param array   $groupBy
     * @param array   $orderBy
     * @param int     $limit
     * @param int     $offset
     * @param array   $unions
     * @return string
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
        //This statement parts should be processed first to define set of table and column aliases
        $from = $this->tables($from);
        $joins = $joins ? $this->joins($joins) . ' ' : '';

        $distinct = $distinct ? ' ' . $this->distinct($distinct) . ' ' : '';
        $columns = $this->columns($columns);

        //Conditions
        $where = $where ? "\nWHERE " . $this->where($where) . ' ' : '';
        $having = $having ? "\nHAVING " . $this->where($having) . ' ' : '';

        //Sortings and grouping
        $groupBy = $groupBy ? "\nGROUP BY " . $this->groupBy($groupBy) . ' ' : '';

        //Initial statement have predictable order
        $statement = rtrim(
                "SELECT{$distinct}\n{$columns}" . "\nFROM {$from} {$joins}{$where}{$groupBy}{$having}"
            ) . ' ';

        if (empty($unions) && !empty($orderBy))
        {
            $statement .= "\nORDER BY " . $this->orderBy($orderBy);
        }

        if (!empty($unions))
        {
            $statement .= $this->unions($unions);
        }

        if (!empty($unions) && !empty($orderBy))
        {
            $statement .= "\nORDER BY " . $this->orderBy($orderBy);
        }

        if ($limit || $offset)
        {
            $statement .= "\n" . $this->limit($limit, $offset);
        }

        return rtrim($statement);
    }

    /**
     * Query query identifier, if identified stated as table - table prefix must be added.
     *
     * @param string $identifier  Identifier can include simple column operations and functions,
     *                            having "." in it will automatically force table prefix to first value.
     * @param bool   $table       Set to true to let quote method know that identified is related to
     *                            table name.
     * @param bool   $forceTable  In some cases we have to force prefix.
     * @return mixed|string
     */
    public function quote($identifier, $table = false, $forceTable = false)
    {
        if ($identifier instanceof SQLFragmentInterface)
        {
            return $identifier->sqlStatement($this);
        }

        if (preg_match('/ as /i', $identifier, $matches))
        {
            list($identifier, $alias) = explode($matches[0], $identifier);

            /**
             * We can't do looped aliases, so let's force table prefix for identifier if we aliasing
             * table name at this moment.
             */
            $quoted = $this->quote($identifier, $table, $table)
                . $matches[0]
                . $this->identifier($alias);

            if ($table && strpos($identifier, '.') === false)
            {
                //We have to apply operation post factum to prevent self aliasing (name AS name
                //when db has prefix, expected: prefix_name as name)
                $this->aliases[$alias] = $identifier;
            }

            return $quoted;
        }

        if (strpos($identifier, '(') || strpos($identifier, ' '))
        {
            return preg_replace_callback('/([a-z][0-9_a-z\.]*\(?)/i', function ($identifier) use (&$table)
            {
                $identifier = $identifier[1];
                if (substr($identifier, -1) == '(')
                {
                    //Function name
                    return $identifier;
                }

                if ($table)
                {
                    $table = false;

                    //Only first table has to be escaped
                    return $this->quote($identifier, true);
                }

                return $this->quote($identifier);
            }, $identifier);
        }

        if (strpos($identifier, '.') === false)
        {
            if (($table && !isset($this->aliases[$identifier])) || $forceTable)
            {
                if (!isset($this->aliases[$this->tablePrefix . $identifier]))
                {
                    $this->aliases[$this->tablePrefix . $identifier] = $identifier;
                }

                $identifier = $this->tablePrefix . $identifier;
            }

            return $this->identifier($identifier);
        }

        $identifier = explode('.', $identifier);

        //Expecting first element be table name
        if (!isset($this->aliases[$identifier[0]]))
        {
            $identifier[0] = $this->tablePrefix . $identifier[0];
        }

        //No aliases can be collected there
        $identifier = array_map([$this->driver, 'identifier'], $identifier);

        return join('.', $identifier);
    }

    /**
     * Driver specific database/table identifier quotation.
     *
     * @param string $identifier
     * @return string
     */
    public function identifier($identifier)
    {
        return $identifier == '*' ? '*' : '"' . str_replace('"', '""', $identifier) . '"';
    }

    /**
     * Sort list of parameters in sql specific order, query type must be provided.
     *
     * @param int   $type
     * @param array $where
     * @param array $joins
     * @param array $having
     * @param array $columns
     * @return array
     */
    public function prepareParameters(
        $type,
        array $where = [],
        array  $joins = [],
        array $having = [],
        array $columns = []
    )
    {
        return array_merge($columns, $joins, $where, $having);
    }

    /**
     * Reset compiler aliases cache.
     *
     * @return $this
     */
    public function reset()
    {
        $this->aliases = [];

        return $this;
    }

    /**
     * Prepare columns to be used in UPDATE statement.
     *
     * @param array  $columns
     * @param string $tableAlias Forced table alias for updated columns.
     * @return array
     */
    protected function prepareColumns(array $columns, $tableAlias = '')
    {
        foreach ($columns as $column => &$value)
        {
            if ($value instanceof QueryBuilder)
            {
                $value = '(' . $value->sqlStatement($this) . ')';
            }
            elseif ($value instanceof SQLFragmentInterface)
            {
                $value = $value->sqlStatement($this);
            }
            else
            {
                $value = '?';
            }

            if (strpos($column, '.') === false && !empty($tableAlias))
            {
                $column = $tableAlias . '.' . $column;
            }

            $value = ' ' . $this->quote($column) . ' = ' . $value;

            unset($value);
        }

        return join(", ", $columns);
    }

    /**
     * Compile DISTINCT statement.
     *
     * @param mixed $distinct
     * @return string
     */
    protected function distinct($distinct)
    {
        return "DISTINCT";
    }

    /**
     * Compile table names statement.
     *
     * @param array $tables
     * @return string
     */
    protected function tables(array $tables)
    {
        foreach ($tables as &$table)
        {
            $table = $this->quote($table, true, true);
            unset($table);
        }

        return join(', ', $tables);
    }

    /**
     * Compile columns list statement.
     *
     * @param array $columns
     * @return string
     */
    protected function columns(array $columns)
    {
        return wordwrap(join(', ', array_map([$this, 'quote'], $columns)), 180);
    }

    /**
     * Compiler joins statement.
     *
     * @param array $joins
     * @return string
     */
    protected function joins(array $joins)
    {
        $statement = '';
        foreach ($joins as $table => $join)
        {
            $statement .= "\n" . $join['type'] . ' JOIN ' . $this->quote($table, true, true);

            if (!empty($join['on']))
            {
                $statement .= "\n    ON " . $this->where($join['on']);
            }
        }

        return $statement;
    }

    /**
     * Compile where statement.
     *
     * @param array $tokens
     * @return string
     * @throws CompilerException
     */
    protected function where(array $tokens)
    {
        if (empty($tokens))
        {
            return '';
        }

        $statement = '';

        $activeGroup = true;
        foreach ($tokens as $condition)
        {
            $joiner = $condition[0];
            $context = $condition[1];

            //First condition in group/query, no any AND, OR required
            if ($activeGroup)
            {
                //Kill AND, OR and etc.
                $joiner = '';

                //Next conditions require AND or OR
                $activeGroup = false;
            }
            else
            {
                $joiner .= ' ';
            }

            if ($context == '(')
            {
                //New where group.
                $activeGroup = true;
            }

            if (is_string($context))
            {
                $statement = rtrim($statement . $joiner)
                    . ($joiner && $context == '(' ? ' ' : '')
                    . $context
                    . ($context == ')' ? ' ' : '');

                continue;
            }

            if ($context instanceof QueryBuilder)
            {
                $statement .= $joiner . ' (' . $context->sqlStatement($this) . ') ';
                continue;
            }

            if ($context instanceof SQLFragmentInterface)
            {
                //( ?? )
                $statement .= $joiner . ' ' . $context->sqlStatement($this) . ' ';
                continue;
            }

            list($identifier, $operator, $value) = $context;
            if ($identifier instanceof QueryBuilder)
            {
                $identifier = '(' . $identifier->sqlStatement($this) . ')';
            }
            elseif ($identifier instanceof SQLFragmentInterface)
            {
                $identifier = $identifier->sqlStatement($this);
            }
            else
            {
                $identifier = $this->quote($identifier);
            }

            if ($operator == 'BETWEEN' || $operator == 'NOT BETWEEN')
            {
                $statement .= "{$joiner} {$identifier} " . "{$operator} "
                    . "{$this->getPlaceholder($value)} AND {$this->getPlaceholder($context[3])} ";

                continue;
            }

            if ($value === null || ($value instanceof ParameterInterface && $value->getValue() === null))
            {
                $operator = $operator == '=' ? 'IS' : 'IS NOT';
            }

            if (
                $operator == '='
                && (
                    is_array($value)
                    || ($value instanceof ParameterInterface && is_array($value->getValue()))
                )
            )
            {
                $operator = 'IN';
            }

            if ($value instanceof QueryBuilder)
            {
                $value = ' (' . $value . ') ';
            }
            else
            {
                $value = $this->getPlaceholder($value);
            }

            $statement .= "{$joiner}{$identifier} {$operator} {$value} ";
        }

        if ($activeGroup)
        {
            throw new CompilerException("Unable to build where statement, unclosed where group.");
        }

        return trim($statement);
    }

    /**
     * Prepare value to be replaced into query (replace ?).
     *
     * @param string $value
     * @return string
     */
    protected function getPlaceholder($value)
    {
        if ($value instanceof SQLFragmentInterface)
        {
            return $value->sqlStatement($this);
        }

        return '?';
    }

    /**
     * Compile union statement chunk. Keywords UNION and ALL will be included, this methods will
     * automatically move every union on new line.
     *
     * @param array $unions
     * @return string
     */
    protected function unions(array $unions)
    {
        $statement = '';
        foreach ($unions as $union)
        {
            $statement .= "\nUNION {$union[1]} \n({$union[0]})";
        }

        return $statement;
    }

    /**
     * Compile ORDER BY statement.
     *
     * @param array $orderBy
     * @return string
     */
    protected function orderBy(array $orderBy)
    {
        $statement = '';
        foreach ($orderBy as $item)
        {
            $statement .= $this->quote($item[0]) . ' ' . strtoupper($item[1]);
        }

        return $statement;
    }

    /**
     * Compiler GROUP BY statement.
     *
     * @param array $groupBy
     * @return string
     */
    protected function groupBy(array $groupBy)
    {
        $statement = '';
        foreach ($groupBy as $identifier)
        {
            $statement .= $this->quote($identifier);
        }

        return $statement;
    }

    /**
     * Compile limit statement.
     *
     * @param int $limit
     * @param int $offset
     * @return string
     */
    protected function limit($limit, $offset)
    {
        $statement = '';
        if (!empty($limit))
        {
            $statement = "LIMIT {$limit} ";
        }

        if (!empty($offset))
        {
            $statement .= "OFFSET {$offset}";
        }

        return trim($statement);
    }

    /**
     * Helper method used to interpolate SQL query with set of parameters, must be used only for
     * development purposes and never for real query.
     *
     * @param string $query
     * @param array  $parameters Parameters to be binded into query.
     * @return mixed
     */
    public static function interpolate($query, array $parameters = [])
    {
        if (empty($parameters))
        {
            return $query;
        }

        array_walk($parameters, function (&$parameter)
        {
            switch (gettype($parameter))
            {
                case "boolean":
                    return $parameter = $parameter ? 'true' : 'false';
                case "integer":
                    return $parameter = $parameter + 0;
                case "NULL":
                    return $parameter = 'NULL';
                case "double":
                    return $parameter = sprintf('%F', $parameter);
                case "string":
                    return $parameter = "'" . addcslashes($parameter, "'") . "'";
                case 'object':
                    if (method_exists($parameter, '__toString'))
                    {
                        return $parameter = "'" . addcslashes((string)$parameter, "'") . "'";
                    }
            }

            return $parameter = "[UNRESOLVED]";
        });

        reset($parameters);
        if (!is_int(key($parameters)))
        {
            return \Spiral\interpolate($query, $parameters, '', '');
        }

        foreach ($parameters as $parameter)
        {
            $query = preg_replace('/\?/', $parameter, $query, 1);
        }

        return $query;
    }
}