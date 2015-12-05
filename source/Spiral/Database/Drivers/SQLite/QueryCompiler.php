<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Database\Drivers\SQLite;

use Spiral\Database\Entities\QueryCompiler as AbstractCompiler;

/**
 * SQLite specific syntax compiler.
 */
class QueryCompiler extends AbstractCompiler
{
    /**
     * {@inheritdoc}
     */
    public function compileInsert($table, array $columns, array $rowsets)
    {
        if (count($rowsets) == 1) {
            return parent::compileInsert($table, $columns, $rowsets);
        }

        //SQLite uses alternative syntax
        $statement = [];
        $statement[] = "INSERT INTO {$this->quote($table, true)} ({$this->prepareColumns($columns)})";

        foreach ($rowsets as $rowset) {
            if (count($statement) == 1) {
                $selectColumns = [];
                foreach ($columns as $column) {
                    $selectColumns[] = "? AS {$this->quote($column)}";
                }

                $statement[] = 'SELECT ' . join(', ', $selectColumns);
            } else {
                $statement[] = 'UNION SELECT ' . trim(str_repeat('?, ', count($columns)), ', ');
            }
        }

        return join("\n", $statement);
    }

    /**
     * {@inheritdoc}
     *
     * @link http://stackoverflow.com/questions/10491492/sqllite-with-skip-offset-only-not-limit
     */
    protected function compileLimit($limit, $offset)
    {
        if (empty($limit) && empty($offset)) {
            return '';
        }

        $statement = '';

        if (!empty($limit) || !empty($offset)) {
            $statement = "LIMIT " . ($limit ?: '-1') . " ";
        }

        if (!empty($offset)) {
            $statement .= "OFFSET {$offset}";
        }

        return trim($statement);
    }
}