<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Drivers\Postgres;

use Spiral\Database\QueryCompiler as AbstractCompiler;

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
    public function delete($table, array $joins = [], array $where = [])
    {
        if (empty($joins))
        {
            return parent::delete($table, $joins, $where);
        }

        //Situation is little bit more complex when we have joins
        $statement = parent::delete($table);

        //We have to rebuild where tokens
        $whereTokens = [];

        //Converting JOINS into USING tables
        $usingTables = [];
        foreach ($joins as $table => $join)
        {
            $usingTables[] = $this->quote($table, true, true);
            $whereTokens = array_merge($whereTokens, $join['on']);
        }

        $statement .= "\nUSING " . join(', ', $usingTables);

        $whereTokens[] = ['AND', '('];
        $whereTokens = array_merge($whereTokens, $where);
        $whereTokens[] = ['', ')'];

        if (!empty($whereTokens))
        {
            $statement .= "\nWHERE " . $this->where($whereTokens);
        }

        return rtrim($statement);
    }

    /**
     * {@inheritdoc}
     */
    public function update($table, array $columns, array $joins = [], array $where = [])
    {
        if (empty($joins))
        {
            return parent::update($table, $columns, $joins, $where);
        }

        $statement = 'UPDATE ' . $this->quote($table, true, true);

        //We have to rebuild where tokens
        $whereTokens = [];

        //Converting JOINS into FROM tables
        $fromTables = [];
        foreach ($joins as $table => $join)
        {
            $fromTables[] = $this->quote($table, true, true);
            $whereTokens = array_merge($whereTokens, $join['on']);
        }

        $statement .= "\nSET" . $this->prepareColumns($columns);
        $statement .= "\nFROM " . join(', ', $fromTables);

        $whereTokens[] = ['AND', '('];
        $whereTokens = array_merge($whereTokens, $where);
        $whereTokens[] = ['', ')'];

        if (!empty($whereTokens))
        {
            $statement .= "\nWHERE " . $this->where($whereTokens);
        }

        return rtrim($statement);
    }

    /**
     * {@inheritdoc}
     */
    protected function distinct($distinct)
    {
        return "DISTINCT" . (is_string($distinct) ? '(' . $this->quote($distinct) . ')' : '');
    }
}