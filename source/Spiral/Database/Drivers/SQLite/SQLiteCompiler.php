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
class SQLiteCompiler extends AbstractCompiler
{
    /**
     * {@inheritdoc}
     */
    public function compileInsert(string $table, array $columns, array $rowsets): string
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

                $statement[] = 'SELECT ' . implode(', ', $selectColumns);
            } else {
                $statement[] = 'UNION SELECT ' . trim(str_repeat('?, ', count($columns)), ', ');
            }
        }

        return implode("\n", $statement);
    }

    /**
     * {@inheritdoc}
     *
     * @link http://stackoverflow.com/questions/10491492/sqllite-with-skip-offset-only-not-limit
     */
    protected function compileLimit(int $limit, int $offset): string
    {
        if (empty($limit) && empty($offset)) {
            return '';
        }

        $statement = '';

        if (!empty($limit) || !empty($offset)) {
            $statement = 'LIMIT ' . ($limit ?: '-1') . ' ';
        }

        if (!empty($offset)) {
            $statement .= "OFFSET {$offset}";
        }

        return trim($statement);
    }
}
