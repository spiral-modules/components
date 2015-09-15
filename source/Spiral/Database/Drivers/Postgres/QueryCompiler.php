<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
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
    public function insert($table, array $columns, array $rowsets, $primaryKey = '')
    {
        return parent::insert($table, $columns, $rowsets) . (!empty($primaryKey)
            ? ' RETURNING ' . $this->quote($primaryKey)
            : ''
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function distinct($distinct)
    {
        if (empty($distinct)) {
            return '';
        }

        return "DISTINCT" . (is_string($distinct) ? '(' . $this->quote($distinct) . ')' : '');
    }
}