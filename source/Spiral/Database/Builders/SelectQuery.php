<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Builders;

use Spiral\Database\Builders\Prototypes\AbstractSelect;
use Spiral\Database\Entities\Database;
use Spiral\Database\Entities\QueryCompiler;
use Spiral\Database\Injections\SQLFragmentInterface;
use Spiral\Database\Entities\QueryBuilder;

/**
 * SelectQuery extends AbstractSelect with ability to specify selection tables and perform UNION
 * of multiple select queries.
 */
class SelectQuery extends AbstractSelect
{
    /**
     * Table names to select data from.
     *
     * @var array
     */
    protected $tables = [];

    /**
     * Select queries represented by sql fragments or query builders to be united. Stored as
     * [UNION TYPE, SELECT QUERY].
     *
     * @var array
     */
    protected $unions = [];

    /**
     * @param Database      $database Parent database.
     * @param QueryCompiler $compiler Driver specific QueryGrammar instance (one per builder).
     * @param array         $from     Initial set of table names.
     * @param array         $columns  Initial set of columns to fetch.
     */
    public function __construct(
        Database $database,
        QueryCompiler $compiler,
        array $from = [],
        array $columns = []
    ) {
        parent::__construct($database, $compiler);

        $this->tables = $from;
        if (!empty($columns)) {
            $this->columns = $this->fetchIdentifiers($columns);
        }
    }

    /**
     * Set table names SELECT query should be performed for. Table names can be provided with specified
     * alias (AS construction).
     *
     * @param array|string|mixed $tables Array of names, comma separated string or set of parameters.
     * @return $this
     */
    public function from($tables)
    {
        $this->tables = $this->fetchIdentifiers(func_get_args());

        return $this;
    }

    /**
     * Set columns should be fetched as result of SELECT query. Columns can be provided with specified
     * alias (AS construction).
     *
     * @param array|string|mixed $columns Array of names, comma separated string or set of parameters.
     * @return $this
     */
    public function columns($columns)
    {
        $this->columns = $this->fetchIdentifiers(func_get_args());

        return $this;
    }

    /**
     * Alias for columns() method.
     *
     * @param array|string|mixed $columns Array of names, comma separated string or set of parameters.
     * @return $this
     */
    public function select($columns)
    {
        $this->columns = $this->fetchIdentifiers(func_get_args());

        return $this;
    }

    /**
     * Add select query to be united with.
     *
     * @param SQLFragmentInterface $query
     * @return $this
     */
    public function union(SQLFragmentInterface $query)
    {
        $this->unions[] = ['', $query];

        return $this;
    }

    /**
     * Add select query to be united with. Duplicate values will be included in result.
     *
     * @param SQLFragmentInterface $query
     * @return $this
     */
    public function unionAll(SQLFragmentInterface $query)
    {
        $this->unions[] = ['ALL', $query];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getParameters(QueryCompiler $compiler = null)
    {
        $parameters = parent::getParameters(
            $compiler = !empty($compiler) ? $compiler : $this->compiler->reset()
        );

        //Unions always located at the end of query.
        foreach ($this->unions as $union) {
            if ($union[0] instanceof QueryBuilder) {
                $parameters = array_merge($parameters, $union[0]->getParameters($compiler));
            }
        }

        return $parameters;
    }

    /**
     * {@inheritdoc}
     */
    public function sqlStatement(QueryCompiler $compiler = null)
    {
        $compiler = !empty($compiler) ? $compiler : $this->compiler->reset();

        //11 parameters!
        return $compiler->select(
            $this->tables,
            $this->distinct,
            $this->columns,
            $this->joinTokens,
            $this->whereTokens,
            $this->havingTokens,
            $this->groupBy,
            $this->orderBy,
            $this->limit,
            $this->offset,
            $this->unions
        );
    }
}