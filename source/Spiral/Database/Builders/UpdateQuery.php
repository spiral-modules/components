<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Builders;

use Spiral\Database\Builders\Prototypes\AbstractAffect;
use Spiral\Database\Entities\Database;
use Spiral\Database\Entities\QueryCompiler;
use Spiral\Database\Exceptions\BuilderException;
use Spiral\Database\Injections\SQLFragmentInterface;
use Spiral\Database\ParameterInterface;
use Spiral\Database\QueryBuilder;

/**
 * Update statement builder with WHERE and JOINS support.
 */
class UpdateQuery extends AbstractAffect
{
    /**
     * Column names associated with their values.
     *
     * @var array
     */
    protected $values = [];

    /**
     * {@inheritdoc}
     *
     * @param array $values Initial set of column updates.
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
        $this->values = $values;
    }

    /**
     * Change target table.
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
     * Change value set to be updated, must be represented by array of columns associated with new
     * value to be set.
     *
     * @param array $values
     * @return $this
     */
    public function values(array $values)
    {
        $this->values = $values;

        return $this;
    }

    /**
     * Get list of columns associated with their values.
     *
     * @return array
     */
    public function getValues()
    {
        return $this->values;
    }

    /**
     * Set update value.
     *
     * @param string $column
     * @param mixed  $value
     * @return $this
     */
    public function set($column, $value)
    {
        $this->values[$column] = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getParameters(QueryCompiler $compiler = null)
    {
        $compiler = !empty($compiler) ? $compiler : $this->compiler;

        $values = [];
        foreach ($this->values as $value)
        {
            if ($value instanceof QueryBuilder)
            {
                foreach ($value->getParameters() as $parameter)
                {
                    $values[] = $parameter;
                }

                continue;
            }

            if ($value instanceof SQLFragmentInterface && !$value instanceof ParameterInterface)
            {
                continue;
            }

            $values[] = $value;
        }

        //Join and where parameters are going after values
        return $this->flattenParameters($compiler->prepareParameters(
            QueryCompiler::UPDATE_QUERY, $this->whereParameters, $this->onParameters, [], $values
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function sqlStatement(QueryCompiler $compiler = null)
    {
        if (empty($this->values))
        {
            throw new BuilderException("Update values must be specified.");
        }

        $compiler = !empty($compiler) ? $compiler : $this->compiler->reset();

        return $compiler->update($this->table, $this->values, $this->joinTokens, $this->whereTokens);
    }
}