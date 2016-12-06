<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Builders;

use Spiral\Database\Builders\Prototypes\AbstractSelect;
use Spiral\Database\Entities\Database;
use Spiral\Database\Entities\QueryBuilder;
use Spiral\Database\Entities\QueryCompiler;
use Spiral\Database\Injections\FragmentInterface;

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
    protected $unionTokens = [];

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
     * Set table names SELECT query should be performed for. Table names can be provided with
     * specified alias (AS construction).
     *
     * @param array|string|mixed $tables Array of names, comma separated string or set of
     *                                   parameters.
     *
     * @return $this
     */
    public function from($tables)
    {
        $this->tables = $this->fetchIdentifiers(func_get_args());

        return $this;
    }

    /**
     * Set columns should be fetched as result of SELECT query. Columns can be provided with
     * specified alias (AS construction).
     *
     * @param array|string|mixed $columns Array of names, comma separated string or set of
     *                                    parameters.
     *
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
     * @param array|string|mixed $columns Array of names, comma separated string or set of
     *                                    parameters.
     *
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
     * @param FragmentInterface $query
     *
     * @return $this
     */
    public function union(FragmentInterface $query)
    {
        $this->unionTokens[] = ['', $query];

        return $this;
    }

    /**
     * Add select query to be united with. Duplicate values will be included in result.
     *
     * @param FragmentInterface $query
     *
     * @return $this
     */
    public function unionAll(FragmentInterface $query)
    {
        $this->unionTokens[] = ['ALL', $query];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getParameters(QueryCompiler $compiler = null)
    {
        if (empty($compiler)) {
            $compiler = $this->compiler;
        }

        $parameters = parent::getParameters($compiler);

        //Unions always located at the end of query.
        foreach ($this->unionTokens as $union) {
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
        if (empty($compiler)) {
            $compiler = $this->compiler->resetQuoter();
        }

        //11 parameters!
        return $compiler->compileSelect(
            $this->tables,
            $this->distinct,
            $this->columns,
            $this->joinTokens,
            $this->whereTokens,
            $this->havingTokens,
            $this->grouping,
            $this->ordering,
            $this->getLimit(),
            $this->getOffset(),
            $this->unionTokens
        );
    }

    /**
     * Request all results as array.
     *
     * @return array
     */
    public function all()
    {
        return $this->getIterator()->fetchAll(\PDO::FETCH_ASSOC);
    }
}
