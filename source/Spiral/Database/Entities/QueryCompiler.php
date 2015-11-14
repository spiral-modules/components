<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Database\Entities;

use Spiral\Core\Component;
use Spiral\Database\Entities\Compiler\Quoter;
use Spiral\Database\Exceptions\CompilerException;
use Spiral\Database\Injections\ExpressionInterface;
use Spiral\Database\Injections\FragmentInterface;
use Spiral\Database\Injections\ParameterInterface;

/**
 * Responsible for conversion of set of query parameters (where tokens, table names and etc) into
 * sql to be send into specific Driver.
 *
 * Source of Compiler must be optimized in nearest future.
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
     * Associated driver instance, may be required for some data assumptions.
     *
     * @var PDODriver
     */
    protected $driver = null;

    /**
     * Quotes names and expressions.
     *
     * @var Quoter
     */
    protected $quoter = null;

    /**
     * QueryCompiler constructor.
     *
     * @param PDODriver $driver
     * @param Quoter    $quoter
     */
    public function __construct(PDODriver $driver, Quoter $quoter)
    {
        $this->driver = $driver;
        $this->quoter = $quoter;
    }

    /**
     * Reset table aliases cache, required if same compiler used twice.
     */
    public function resetQuoter()
    {
        $this->quoter->reset();
    }

    /**
     * Sort list of parameters in dbms query specific order, query type must be provided. This
     * method was used at times when delete and update queries supported joins, i might need to
     * drop it now.
     *
     * @param int   $queryType
     * @param array $whereParameters
     * @param array $onParameters
     * @param array $havingParameters
     * @param array $columnIdentifiers Column names (if any).
     * @return array
     */
    public function orderParameters(
        $queryType,
        array $whereParameters = [],
        array $onParameters = [],
        array $havingParameters = [],
        array $columnIdentifiers = []
    ) {
        return array_merge($columnIdentifiers, $onParameters, $whereParameters, $havingParameters);
    }

    /**
     * Create insert query using table names, columns and rowsets. Must support both - single and
     * batch inserts.
     *
     * @param string              $table
     * @param array               $columns
     * @param FragmentInterface[] $rowsets Every rowset has to be convertable into string. Raw data
     *                                     not allowed!
     * @return string
     * @throws CompilerException
     */
    public function compileInsert($table, array $columns, array $rowsets)
    {
        if (empty($columns)) {
            throw new CompilerException("Unable to build insert statement, columns must be set.");
        }

        if (empty($rowsets)) {
            throw new CompilerException(
                "Unable to build insert statement, at least one value set must be provided."
            );
        }

        //To add needed prefixes (if any)
        $table = $this->quote($table, true);

        //Compiling list of columns
        $columns = $this->prepareColumns($columns);

        //Simply joining every rowset
        $rowsets = join(",\n", $rowsets);

        return "INSERT INTO {$table} ({$columns})\nVALUES {$rowsets}";
    }

    /**
     * Create update statement.
     *
     * @param string $table
     * @param array  $updates
     * @param array  $whereTokens
     * @return string
     * @throws CompilerException
     */
    public function compileUpdate($table, array $updates, array $whereTokens = [])
    {
        $table = $this->quote($table, true);

        //Preparing update column statement
        $updates = $this->prepareUpdates($updates);

        //Where statement is optional for update queries
        $whereStatement = $this->optional("\nWHERE", $this->compileWhere($whereTokens));

        return rtrim("UPDATE {$table}\nSET {$updates} {$whereStatement}");
    }

    /**
     * Create delete statement.
     *
     * @param string $table
     * @param array  $whereTokens
     * @return string
     * @throws CompilerException
     */
    public function compileDelete($table, array $whereTokens = [])
    {
        $table = $this->quote($table, true);

        //Where statement is optional for delete query (which is weird)
        $whereStatement = $this->optional("\nWHERE", $this->compileWhere($whereTokens));

        return rtrim("DELETE FROM {$table} {$whereStatement}");
    }

    /**
     * Create select statement. Compiler must validly resolve table and column aliases used in
     * conditions and joins.
     *
     * @param array          $fromTables
     * @param boolean|string $distinct String only for PostgresSQL.
     * @param array          $columns
     * @param array          $joinTokens
     * @param array          $whereTokens
     * @param array          $havingTokens
     * @param array          $grouping
     * @param array          $ordering
     * @param int            $limit
     * @param int            $offset
     * @param array          $unionTokens
     * @return string
     * @throws CompilerException
     */
    public function compileSelect(
        array $fromTables,
        $distinct,
        array $columns,
        array $joinTokens = [],
        array $whereTokens = [],
        array $havingTokens = [],
        array $grouping = [],
        array $ordering = [],
        $limit = 0,
        $offset = 0,
        array $unionTokens = []
    ) {
        //This statement parts should be processed first to define set of table and column aliases
        $fromTables = $this->compileTables($fromTables);

        $joinsStatement = $this->optional(' ', $this->compileJoins($joinTokens), ' ');

        //Distinct flag (if any)
        $distinct = $this->optional(' ', $this->compileDistinct($distinct));

        //Columns are compiled after table names and joins to enshure aliases and prefixes
        $columns = $this->prepareColumns($columns);

        //A lot of constrain and other statements
        $whereStatement = $this->optional("\nWHERE", $this->compileWhere($whereTokens));
        $havingStatement = $this->optional("\nHAVING", $this->compileWhere($havingTokens));
        $groupingStatement = $this->optional("\nGROUP BY", $this->compileGrouping($grouping), ' ');

        //Union statement has new line at beginning of every union
        $unionsStatement = $this->optional("\n", $this->compileUnions($unionTokens));
        $orderingStatement = $this->optional("\nORDER BY ", $this->compileOrdering($ordering));

        $limingStatement = $this->optional("\n", $this->compileLimit($limit, $offset));

        //Initial statement have predictable order
        $statement = "SELECT{$distinct}\n{$columns}\nFROM {$fromTables}";
        $statement .= "{$joinsStatement}{$whereStatement}{$groupingStatement}{$havingStatement}";
        $statement .= "{$unionsStatement}{$orderingStatement}{$limingStatement}";

        return rtrim($statement);
    }

    /**
     * Quote and wrap column identifiers (used in insert statement compilation).
     *
     * @param array $columnIdentifiers
     * @param int   $maxLength Automatically wrap columns.
     * @return string
     */
    protected function prepareColumns(array $columnIdentifiers, $maxLength = 180)
    {
        //Let's quote every identifier
        $columnIdentifiers = array_map([$this, 'quote'], $columnIdentifiers);

        return wordwrap(join(', ', $columnIdentifiers), $maxLength);
    }

    /**
     * Prepare column values to be used in UPDATE statement.
     *
     * @param array $updates
     * @return array
     */
    protected function prepareUpdates(array $updates)
    {
        foreach ($updates as $column => &$value) {

            if ($value instanceof QueryBuilder) {
                //Nested query
                $value = '(' . $value->sqlStatement($this) . ')';
            } elseif ($value instanceof ExpressionInterface) {
                //Expression
                $value = $value->sqlStatement($this);
            } elseif ($value instanceof FragmentInterface) {
                //Plain fragment (i forgot why i'm using fragments without compiler)
                $value = $value->sqlStatement();
            } else {
                //Simple value (such condition should never be met since every value has to be
                //wrapped using parameter interface)
                $value = '?';
            }

            $value = "{$this->quote($column)} = {$value}";
            unset($value);
        }

        return trim(join(",", $updates));
    }

    /**
     * Compile DISTINCT statement.
     *
     * @param mixed $distinct Not every DBMS support distinct expression, only Postgres does.
     * @return string
     */
    protected function compileDistinct($distinct)
    {
        if (empty($distinct)) {
            return '';
        }

        return "DISTINCT";
    }

    /**
     * Compile table names statement.
     *
     * @param array $tables
     * @return string
     */
    protected function compileTables(array $tables)
    {
        foreach ($tables as &$table) {
            $table = $this->quote($table, true);
            unset($table);
        }

        return join(', ', $tables);
    }

    /**
     * Compiler joins statement.
     *
     * @param array $joinTokens
     * @return string
     */
    protected function compileJoins(array $joinTokens)
    {
        $statement = '';
        foreach ($joinTokens as $table => $join) {
            $statement .= "\n" . $join['type'] . ' JOIN ' . $this->quote($table, true, true);
            $statement .= $this->optional("\n    ON", $this->compileWhere($join['on']));
        }

        return $statement;
    }

    /**
     * Compile union statement chunk. Keywords UNION and ALL will be included, this methods will
     * automatically move every union on new line.
     *
     * @param array $unionTokens
     * @return string
     */
    protected function compileUnions(array $unionTokens)
    {
        if (empty($unionTokens)) {
            return '';
        }

        $statement = '';
        foreach ($unionTokens as $union) {
            //First key is union type, second united query (no need to share compiler)
            $statement .= "\nUNION {$union[1]}\n({$union[0]})";
        }

        return ltrim($statement, "\n");
    }

    /**
     * Compile ORDER BY statement.
     *
     * @param array $ordering
     * @return string
     */
    protected function compileOrdering(array $ordering)
    {
        $result = [];
        foreach ($ordering as $order) {
            $result[] = $this->quote($order[0]) . ' ' . strtoupper($order[1]);
        }

        return join(', ', $result);
    }

    /**
     * Compiler GROUP BY statement.
     *
     * @param array $grouping
     * @return string
     */
    protected function compileGrouping(array $grouping)
    {
        $statement = '';
        foreach ($grouping as $identifier) {
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
    protected function compileLimit($limit, $offset)
    {
        if (empty($limit) && empty($offset)) {
            return '';
        }

        $statement = '';
        if (!empty($limit)) {
            $statement = "LIMIT {$limit} ";
        }

        if (!empty($offset)) {
            $statement .= "OFFSET {$offset}";
        }

        return trim($statement);
    }

    /**
     * Compile where statement.
     *
     * @param array $tokens
     * @return string
     * @throws CompilerException
     */
    protected function compileWhere(array $tokens)
    {
        if (empty($tokens)) {
            return '';
        }

        $statement = '';

        $activeGroup = true;
        foreach ($tokens as $condition) {
            //OR/AND keyword
            $boolean = $condition[0];

            //See AbstractWhere
            $context = $condition[1];

            //First condition in group/query, no any AND, OR required
            if ($activeGroup) {
                //Kill AND, OR and etc.
                $boolean = '';

                //Next conditions require AND or OR
                $activeGroup = false;
            }

            /**
             * When context is string it usually represent control keyword/syntax such as opening
             * or closing braces.
             */
            if (is_string($context)) {
                if ($context == '(') {
                    //New where group.
                    $activeGroup = true;
                }

                $postfix = ' ';
                if ($context == '(') {
                    //We don't need space after opening brace
                    $postfix = '';
                }

                $statement .= ltrim("{$boolean} {$context}{$postfix}");
                continue;
            }

            if ($context instanceof FragmentInterface) {
                //Fragments has to be compiled separately
                $statement .= "{$boolean} {$this->prepareFragment($context)} ";
                continue;
            }

            if (!is_array($context)) {
                throw new CompilerException(
                    "Invalid where token, context expected to be an array."
                );
            }

            /**
             * This is "normal" where token which includes identifier, operator and value.
             */
            list($identifier, $operator, $value) = $context;

            //Identifier can be column name, expression or even query builder
            $identifier = $this->quote($identifier);

            //Value has to be prepared as well
            $value = $this->prepareValue($value);

            if ($operator == 'BETWEEN' || $operator == 'NOT BETWEEN') {
                //Between statement has additional parameter
                $right = $this->prepareValue($context[3]);

                $statement .= "{$boolean} {$identifier} {$operator} {$value} AND {$right} ";
                continue;
            }

            //Compiler can switch equal to IN if value points to array
            $operator = $this->prepareOperator($value, $operator);

            $statement .= "{$boolean} {$identifier} {$operator} {$value} ";
        }

        if ($activeGroup) {
            throw new CompilerException("Unable to build where statement, unclosed where group.");
        }

        return trim($statement);
    }

    /**
     * Query query identifier, if identified stated as table - table prefix must be added.
     *
     * @param string $identifier Identifier can include simple column operations and functions,
     *                           having "." in it will automatically force table prefix to first
     *                           value.
     * @param bool   $table      Set to true to let quote method know that identified is related
     *                           to table name.
     * @return mixed|string
     */
    protected function quote($identifier, $table = false)
    {
        if ($identifier instanceof FragmentInterface) {
            return $this->prepareFragment($identifier);
        }

        return $this->quoter->quote($identifier, $table);
    }

    /**
     * Combine expression with prefix/postfix (usually SQL keyword) but only if expression is not
     * empty.
     *
     * @param string $prefix
     * @param string $expression
     * @param string $postfix
     * @return string
     */
    protected function optional($prefix, $expression, $postfix = '')
    {
        if (empty($expression)) {
            return '';
        }

        return $prefix . ' ' . $expression . $postfix;
    }

    /**
     * Prepare value to be replaced into query (replace ?).
     *
     * @param string $value
     * @return string
     */
    private function prepareValue($value)
    {
        if ($value instanceof FragmentInterface) {
            return $this->prepareFragment($value);
        }

        //Technically should never happen (but i prefer to keep this legacy code)
        return '?';
    }

    /**
     * Prepare where fragment to be injected into statement.
     *
     * @param FragmentInterface $context
     * @return string
     */
    private function prepareFragment(FragmentInterface $context)
    {
        if ($context instanceof QueryBuilder) {
            //Nested queries has to be wrapped with braces
            return '(' . $context->sqlStatement($this) . ')';
        }

        if ($context instanceof ExpressionInterface) {
            //Fragments does not need braces around them
            return $context->sqlStatement($this);
        }

        return $context->sqlStatement();
    }

    /**
     * Resolve operator value based on value value. ;)
     *
     * @param mixed  $value
     * @param string $operator
     * @return string
     */
    private function prepareOperator($value, $operator)
    {
        if ($operator != '=' || is_scalar($value)) {
            //Doing nothing for non equal operators
            return $operator;
        }

        if ($value instanceof ParameterInterface && is_array($value->getValue())) {
            //Automatically switching between equal and IN
            return 'IN';
        }

        return $operator;
    }
}