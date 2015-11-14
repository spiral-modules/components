<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Database\Entities;

use Spiral\Core\Component;
use Spiral\Database\Exceptions\BuilderException;
use Spiral\Database\Exceptions\QueryException;
use Spiral\Database\Injections\ParameterInterface;

/**
 * QueryBuilder classes generate set of control tokens for query compilers, this is query level
 * abstraction.
 */
abstract class QueryBuilder extends Component
{
    /**
     * @invisible
     * @var Database
     */
    protected $database = null;

    /**
     * @invisible
     * @var QueryCompiler
     */
    protected $compiler = null;

    /**
     * @param Database      $database Parent database.
     * @param QueryCompiler $compiler Driver specific QueryCompiler instance (one per builder).
     */
    public function __construct(Database $database, QueryCompiler $compiler)
    {
        $this->database = $database;
        $this->compiler = $compiler;
    }

    /**
     * Get ordered list of builder parameters. Attention, this method WILL return only
     * ParameterInterface instances in future as scalar parameters will be dropped.
     *
     * @param QueryCompiler $compiler
     * @return array|ParameterInterface[]
     * @throws BuilderException
     */
    abstract public function getParameters(QueryCompiler $compiler = null);

    /**
     * {@inheritdoc}
     *
     * @param QueryCompiler $compiler
     */
    abstract public function sqlStatement(QueryCompiler $compiler = null);

    /**
     * Run built statement against parent database. Might return different values based on specific
     * builder implementation.
     *
     * @return mixed
     * @throws QueryException
     */
    abstract public function run();

    /**
     * Get interpolated (populated with parameters) SQL which will be run against database, please
     * use this method for debugging purposes only.
     *
     * @return string
     */
    public function queryString()
    {
        return $this->compiler->interpolate(
            $this->sqlStatement(),
            $this->database->driver()->flattenParameters($this->getParameters())
        );
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->sqlStatement();
    }

    /**
     * @return object
     */
    public function __debugInfo()
    {
        try {
            $queryString = $this->queryString();
        } catch (\Exception $exception) {
            $queryString = "[ERROR: {$exception->getMessage()}]";
        }

        $debugInfo = [
            'statement' => $queryString,
            'compiler'  => get_class($this->compiler),
            'database'  => $this->database
        ];

        return (object)$debugInfo;
    }

    /**
     * Helper methods used to correctly fetch and split identifiers provided by function
     * parameters.
     * It support array list, string or comma separated list. Attention, this method will not work
     * with complex parameters (such as functions) provided as one comma separated string, please
     * use arrays in this case.
     *
     * @param array $identifiers
     * @return array
     */
    protected function fetchIdentifiers(array $identifiers)
    {
        if (count($identifiers) == 1 && is_string($identifiers[0])) {
            return array_map('trim', explode(',', $identifiers[0]));
        }

        if (count($identifiers) == 1 && is_array($identifiers[0])) {
            return $identifiers[0];
        }

        return $identifiers;
    }

    /**
     * Expand all QueryBuilder parameters to create flatten list.
     *
     * @param array $parameters
     * @return array
     */
    protected function flattenParameters(array $parameters)
    {
        $result = [];
        foreach ($parameters as $parameter) {
            if ($parameter instanceof QueryBuilder) {
                $result = array_merge($result, $parameter->getParameters());
                continue;
            }

            $result[] = $parameter;
        }

        return $result;
    }

    /**
     * Generate PDO statement based on generated sql and parameters.
     *
     * @return \PDOStatement
     */
    protected function pdoStatement()
    {
        return $this->database->statement($this->sqlStatement(), $this->getParameters());
    }
}