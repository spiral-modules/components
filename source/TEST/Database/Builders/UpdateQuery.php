<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Builders;

use Spiral\Database\Database;
use Spiral\Database\DatabaseException;
use Spiral\Database\ParameterInterface;
use Spiral\Database\QueryBuilder;
use Spiral\Database\QueryCompiler;
use Spiral\Database\SqlFragmentInterface;

class UpdateQuery extends AbstractAffect
{
    /**
     * Array of column names associated with values to be updated. Values can include scalar, Parameter
     * or SqlFragment data.
     *
     * @var array
     */
    protected $columns = [];

    /**
     * AffectQuery is query builder used to compile affection (delete, update) queries for one
     * associated table.
     *
     * @param Database      $database Parent database.
     * @param QueryCompiler $compiler Driver specific QueryGrammar instance (one per builder).
     * @param string        $table    Associated table name.
     * @param array         $where    Initial set of where rules specified as array.
     * @param array         $values   Initial set of values to update.
     */
    public function __construct(
        Database $database,
        QueryCompiler $compiler,
        $table = '',
        array $where = [],
        array $values = []
    )
    {
        parent::__construct($database, $compiler, $table, $where);
        $this->columns = $values;
    }

    /**
     * Change target table, table name should be provided without postfix.
     *
     * @param string $into Table name without prefix.
     * @return $this
     */
    public function table($into)
    {
        $this->table = $into;

        return $this;
    }

    /**
     * New set of values to update. Will completely overwrite current presets. Values can include
     * scalar, Parameter or SqlFragment data.
     *
     * @param array $values Array of column names associated with values to be updated.
     * @return $this
     */
    public function values(array $values)
    {
        $this->columns = $values;

        return $this;
    }

    /**
     * Add column value pair.
     *
     * @param string $column
     * @param mixed  $value Scalar, Parameter or SQLFragment. Can be nested query.
     * @return $this
     */
    public function set($column, $value)
    {
        $this->columns[$column] = $value;

        return $this;
    }

    /**
     * Get ordered list of builder parameters.
     *
     * @param QueryCompiler $compiler
     * @return array
     */
    public function getParameters(QueryCompiler $compiler = null)
    {
        $compiler = !empty($compiler) ? $compiler : $this->compiler;

        $values = [];
        foreach ($this->columns as $value)
        {
            if ($value instanceof QueryBuilder)
            {
                foreach ($value->getParameters() as $parameter)
                {
                    $values[] = $parameter;
                }
                continue;
            }

            if ($value instanceof SqlFragmentInterface && !$value instanceof ParameterInterface)
            {
                continue;
            }

            $values[] = $value;
        }

        //Join and where parameters are going after values
        return $this->expandParameters($compiler->prepareParameters(
            QueryCompiler::UPDATE_QUERY,
            $this->whereParameters,
            $this->onParameters,
            [],
            $values
        ));
    }

    /**
     * Get or render SQL statement.
     *
     * @param QueryCompiler $compiler
     * @return string
     */
    public function sqlStatement(QueryCompiler $compiler = null)
    {
        $compiler = !empty($compiler) ? $compiler : $this->compiler->resetAliases();

        if (empty($this->columns))
        {
            throw new DatabaseException("Update values should be specified.");
        }

        return $compiler->update($this->table, $this->columns, $this->joins, $this->whereTokens);
    }
}