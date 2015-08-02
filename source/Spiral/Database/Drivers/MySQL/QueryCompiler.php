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
    public function update($table, array $columns, array $joins = [], array $where = [])
    {
        if (empty($joins))
        {
            return parent::update($table, $columns, $joins, $where);
        }

        $alias = $table;
        if (preg_match('/ as /i', $alias, $matches))
        {
            list(, $alias) = explode($matches[0], $table);
        }
        else
        {
            $table = "{$table} AS {$table}";
        }

        $statement = "UPDATE " . $this->quote($table, true, true);

        if (!empty($joins))
        {
            $statement .= $this->joins($joins);
        }

        $statement .= "\nSET" . $this->prepareColumns($columns, $alias);

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
            $table = "{$table} AS {$table}";
        }

        $statement = 'DELETE ' . $this->quote($alias) . ".*\n"
            . 'FROM ' . $this->quote($table, true, true) . ' ';

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
     */
    public function identifier($identifier)
    {
        return $identifier == '*' ? '*' : '`' . str_replace('`', '``', $identifier) . '`';
    }

    /**
     * {@inheritdoc}
     */
    public function prepareParameters(
        $type,
        array $where = [],
        $joins = [],
        array $having = [],
        array $columns = []
    )
    {
        if ($type == self::UPDATE_QUERY)
        {
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

        if (!empty($limit) || !empty($offset))
        {
            //When limit is not provided but offset does we can replace limit value with PHP_INT_MAX
            $statement = "LIMIT " . ($limit ?: '18446744073709551615') . ' ';
        }

        if (!empty($offset))
        {
            $statement .= "OFFSET {$offset}";
        }

        return trim($statement);
    }
}