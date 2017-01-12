<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Builders;

use Spiral\Database\Entities\Driver;
use Spiral\Database\Entities\QueryCompiler;
use Spiral\Database\Exceptions\BuilderException;
use Spiral\Database\Injections\Parameter;

/**
 * Insert statement query builder, support singular and batch inserts.
 */
class InsertQuery extends QueryBuilder
{
    /**
     * Query type.
     */
    const QUERY_TYPE = QueryCompiler::INSERT_QUERY;

    /**
     * @var string
     */
    protected $table = '';

    /**
     * Column names associated with insert.
     *
     * @var array
     */
    protected $columns = [];

    /**
     * Rowsets to be inserted.
     *
     * @var array
     */
    protected $rowsets = [];

    /**
     * {@inheritdoc}
     *
     * @param string $table Associated table name.
     */
    public function __construct(Driver $driver, QueryCompiler $compiler, string $table = '')
    {
        parent::__construct($driver, $compiler);
        $this->table = $table;
    }

    /**
     * Set target insertion table.
     *
     * @param string $into
     *
     * @return self
     */
    public function into(string $into): InsertQuery
    {
        $this->table = $into;

        return $this;
    }

    /**
     * Set insertion column names. Names can be provided as array, set of parameters or comma
     * separated string.
     *
     * Examples:
     * $insert->columns(["name", "email"]);
     * $insert->columns("name", "email");
     * $insert->columns("name, email");
     *
     * @param array|string $columns
     *
     * @return self
     */
    public function columns(...$columns): InsertQuery
    {
        $this->columns = $this->fetchIdentifiers($columns);

        return $this;
    }

    /**
     * Set insertion rowset values or multiple rowsets. Values can be provided in multiple forms
     * (method parameters, array of values, array or rowsets). Columns names will be automatically
     * fetched (if not already specified) from first provided rowset based on rowset keys.
     *
     * Examples:
     * $insert->columns("name", "balance")->values("Wolfy-J", 10);
     * $insert->values([
     *      "name" => "Wolfy-J",
     *      "balance" => 10
     * ]);
     * $insert->values([
     *  [
     *      "name" => "Wolfy-J",
     *      "balance" => 10
     *  ],
     *  [
     *      "name" => "Ben",
     *      "balance" => 20
     *  ]
     * ]);
     *
     * @param mixed $rowsets
     *
     * @return self
     */
    public function values($rowsets): InsertQuery
    {
        if (!is_array($rowsets)) {
            return $this->values(func_get_args());
        }

        if (empty($rowsets)) {
            throw new BuilderException("Insert rowsets must not be empty");
        }

        //Checking if provided set is array of multiple
        reset($rowsets);

        if (!is_array($rowsets[key($rowsets)])) {
            if (empty($this->columns)) {
                $this->columns = array_keys($rowsets);
            }

            $this->rowsets[] = new Parameter(array_values($rowsets));
        } else {
            foreach ($rowsets as $rowset) {
                $this->rowsets[] = new Parameter(array_values($rowset));
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getParameters(): array
    {
        return $this->flattenParameters($this->rowsets);
    }

    /**
     * {@inheritdoc}
     */
    public function sqlStatement(QueryCompiler $compiler = null): string
    {
        if (empty($compiler)) {
            $compiler = $this->compiler->resetQuoter();
        }

        return $compiler->compileInsert($this->table, $this->columns, $this->rowsets);
    }

    /**
     * {@inheritdoc}
     */
    public function run()
    {
        //This must execute our query
        $this->pdoStatement();

        return $this->driver->lastInsertID();
    }

    /**
     * Reset all insertion rowsets to make builder reusable (columns still set).
     */
    public function flushValues()
    {
        $this->rowsets = [];
    }
}
