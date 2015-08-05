<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Builders;

use Spiral\Database\Entities\Database;
use Spiral\Database\Entities\QueryCompiler;
use Spiral\Database\Injections\Parameter;
use Spiral\Database\QueryBuilder;

/**
 * Insert statement query builder, support singular and batch inserts.
 */
class InsertQuery extends QueryBuilder
{
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
     * @param Database      $database Parent database.
     * @param QueryCompiler $compiler Driver specific QueryGrammar instance (one per builder).
     * @param string        $table    Associated table name.
     */
    public function __construct(Database $database, QueryCompiler $compiler, $table = '')
    {
        parent::__construct($database, $compiler);
        $this->table = $table;
    }

    /**
     * Set target insertion table.
     *
     * @param string $into
     * @return $this
     */
    public function into($into)
    {
        $this->table = $into;

        return $this;
    }

    /**
     * Set insertion column names. Names can be provided as array, set of parameters or comma separated
     * string.
     *
     * Examples:
     * $insert->columns(["name", "email"]);
     * $insert->columns("name", "email");
     * $insert->columns("name, email");
     *
     * @param array|string $columns
     * @return $this
     */
    public function columns($columns)
    {
        $this->columns = $this->fetchIdentifiers(func_get_args());

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
     * @return $this
     */
    public function values($rowsets)
    {
        if (!is_array($rowsets)) {
            return $this->values(func_get_args());
        }

        //Checking if provided set is array of multiple
        reset($rowsets);

        if (!is_array($rowsets[key($rowsets)])) {
            $this->columns = array_keys($rowsets);
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
    public function getParameters(QueryCompiler $compiler = null)
    {
        $compiler = !empty($compiler) ? $compiler : $this->compiler;

        return $this->flattenParameters($compiler->prepareParameters(
            QueryCompiler::INSERT_QUERY, [], [], [], $this->rowsets
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function sqlStatement(QueryCompiler $compiler = null)
    {
        $compiler = !empty($compiler) ? $compiler : $this->compiler->reset();

        return $compiler->insert($this->table, $this->columns, $this->rowsets);
    }

    /**
     * {@inheritdoc}
     */
    public function run()
    {
        parent::run();

        return $this->database->driver()->lastInsertID();
    }

    /**
     * Reset all insertion rowsets to make builder reusable (columns still set).
     */
    public function flushValues()
    {
        $this->rowsets = [];
    }
}