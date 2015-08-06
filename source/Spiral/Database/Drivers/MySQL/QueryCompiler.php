<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Drivers\MySQL;

use Spiral\Database\Entities\QueryCompiler as AbstractCompiler;

/**
 * MySQL syntax specific compiler.
 */
class QueryCompiler extends AbstractCompiler
{
    /**
     * {@inheritdoc}
     */
    public function prepareParameters(
        $type,
        array $where = [],
        $joins = [],
        array $having = [],
        array $columns = []
    ) {
        if ($type == self::UPDATE_QUERY) {
            //Where statement has pretty specific order
            return array_merge($joins, $columns, $where);
        }

        return parent::prepareParameters($type, $where, $joins, $having, $columns);
    }

    /**
     * {@inheritdoc}
     *
     * @link http://dev.mysql.com/doc/refman/5.0/en/select.html#id4651990
     */
    protected function limit($limit, $offset)
    {
        $statement = '';

        if (!empty($limit) || !empty($offset)) {
            //When limit is not provided but offset does we can replace limit value with PHP_INT_MAX
            $statement = "LIMIT " . ($limit ?: '18446744073709551615') . ' ';
        }

        if (!empty($offset)) {
            $statement .= "OFFSET {$offset}";
        }

        return trim($statement);
    }
}