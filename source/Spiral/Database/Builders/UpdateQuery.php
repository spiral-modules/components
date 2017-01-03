<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Builders;

use Spiral\Database\Builders\Prototypes\AbstractAffect;
use Spiral\Database\Entities\Driver;
use Spiral\Database\Entities\QueryCompiler;
use Spiral\Database\Exceptions\BuilderException;
use Spiral\Database\Injections\FragmentInterface;
use Spiral\Database\Injections\ParameterInterface;

/**
 * Update statement builder.
 */
class UpdateQuery extends AbstractAffect
{
    /**
     * Query type.
     */
    const QUERY_TYPE = QueryCompiler::UPDATE_QUERY;

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
        Driver $driver,
        QueryCompiler $compiler,
        string $table = '',
        array $where = [],
        array $values = []
    ) {
        parent::__construct($driver, $compiler, $table, $where);

        $this->values = $values;
    }

    /**
     * Change target table.
     *
     * @param string $table Table name without prefix.
     *
     * @return self|$this
     */
    public function in(string $table): UpdateQuery
    {
        $this->table = $table;

        return $this;
    }

    /**
     * Change value set to be updated, must be represented by array of columns associated with new
     * value to be set.
     *
     * @param array $values
     *
     * @return self|$this
     */
    public function values(array $values): UpdateQuery
    {
        $this->values = $values;

        return $this;
    }

    /**
     * Get list of columns associated with their values.
     *
     * @return array
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * Set update value.
     *
     * @param string $column
     * @param mixed  $value
     *
     * @return self|$this
     */
    public function set(string $column, $value): UpdateQuery
    {
        $this->values[$column] = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getParameters(): array
    {
        $values = [];
        foreach ($this->values as $value) {
            if ($value instanceof QueryBuilder) {
                foreach ($value->getParameters() as $parameter) {
                    $values[] = $parameter;
                }

                continue;
            }

            if ($value instanceof FragmentInterface && !$value instanceof ParameterInterface) {
                //Apparently sql fragment
                continue;
            }

            $values[] = $value;
        }

        //Join and where parameters are going after values
        return $this->flattenParameters(array_merge($values, $this->whereParameters));
    }

    /**
     * {@inheritdoc}
     */
    public function sqlStatement(QueryCompiler $compiler = null): string
    {
        if (empty($this->values)) {
            throw new BuilderException('Update values must be specified');
        }

        if (empty($compiler)) {
            $compiler = $this->compiler->resetQuoter();
        }

        return $compiler->compileUpdate($this->table, $this->values, $this->whereTokens);
    }
}
