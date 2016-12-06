<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Drivers\Postgres;

use Spiral\Database\Entities\QueryCompiler as AbstractCompiler;

/**
 * Postgres syntax specific compiler.
 */
class QueryCompiler extends AbstractCompiler
{
    /**
     * {@inheritdoc}
     */
    public function compileInsert($table, array $columns, array $rowsets, $primaryKey = '')
    {
        return parent::compileInsert(
            $table,
            $columns,
            $rowsets
        ) . (!empty($primaryKey) ? ' RETURNING ' . $this->quote($primaryKey) : '');
    }

    /**
     * {@inheritdoc}
     */
    protected function compileDistinct($distinct)
    {
        if (empty($distinct)) {
            return '';
        }

        return 'DISTINCT' . (is_string($distinct) ? '(' . $this->quote($distinct) . ')' : '');
    }
}
