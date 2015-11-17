<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Database\Drivers\SQLServer;

use Psr\Log\LoggerAwareInterface;
use Spiral\Database\Entities\QueryCompiler as AbstractCompiler;
use Spiral\Database\Entities\Quoter;
use Spiral\Database\Injections\Fragment;
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
     * @param Quoter          $quoter
     */
    public function __construct(SQLServerDriver $driver, Quoter $quoter)
    {
        parent::__construct($driver, $quoter);
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
    public function compileSelect(
        array $fromTables,
        $distinct,
        array $columns,
        array $joinsStatement = [],
        array $whereTokens = [],
        array $havingTokens = [],
        array $grouping = [],
        array $ordering = [],
        $limit = 0,
        $offset = 0,
        array $unionTokens = []
    ) {
        if (
            empty($limit) && empty($offset)
            || ($this->driver->serverVersion() >= 12 && !empty($ordering))
        ) {
            //When no limits are specified we can use normal query syntax
            return call_user_func_array(['parent', 'compileSelect'], func_get_args());
        }

        if ($this->driver->serverVersion() >= 12) {
            $this->logger()->warning(
                "You can't use query limiting without specifying ORDER BY statement, sql fallback used."
            );
        } else {
            $this->logger()->warning(
                "You are using older version of SQLServer, "
                . "it has some limitation with query limiting and unions."
            );
        }

        if ($ordering) {
            $ordering = "ORDER BY {$this->compileOrdering($ordering)}";
        } else {
            $ordering = "ORDER BY (SELECT NULL)";
        }

        //Will be removed by QueryResult
        $columns[] = new Fragment(
            "ROW_NUMBER() OVER ($ordering) AS {$this->quote(QueryResult::ROW_NUMBER_COLUMN)}"
        );

        //Let's compile MOST of our query :)
        $selection = parent::compileSelect(
            $fromTables,
            $distinct,
            $columns,
            $joinsStatement,
            $whereTokens,
            $havingTokens,
            $grouping,
            [],
            0, //No limit or offset
            0, //No limit or offset
            $unionTokens
        );

        $limitStatement = $this->compileLimit($limit, $offset, QueryResult::ROW_NUMBER_COLUMN);

        return "SELECT * FROM (\n{$selection}\n) AS [selection_alias] {$limitStatement}";
    }

    /**
     * {@inheritdoc}
     *
     * @link http://stackoverflow.com/questions/2135418/equivalent-of-limit-and-offset-for-sql-server
     */
    protected function compileLimit($limit, $offset, $rowNumber = null)
    {
        if (empty($limit) && empty($offset)) {
            return '';
        }

        //Modern SQLServer are easier to work with
        if (empty($rowNumber) && $this->driver->serverVersion() >= 12) {
            $statement = "OFFSET {$offset} ROWS ";

            if (!empty($limit)) {
                $statement .= "FETCH NEXT {$limit} ROWS ONLY";
            }

            return trim($statement);
        }

        $statement = "WHERE {$this->quote($rowNumber)} ";

        //0 = row_number(1)
        $offset = $offset + 1;

        if (!empty($limit)) {
            $statement .= "BETWEEN {$offset} AND " . ($offset + $limit - 1);
        } else {
            $statement .= ">= {$offset}";
        }

        return $statement;
    }
}