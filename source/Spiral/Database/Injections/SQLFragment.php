<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Injections;

use Spiral\Database\Entities\QueryCompiler;

/**
 * Default implementation of SQLFragmentInterface, provides ability to inject custom SQL code into
 * query builders. Usually used to mock database specific functions.
 *
 * Example: ...->where('time_created', '>', new SQLFragment("NOW()"));
 */
class SQLFragment implements SQLFragmentInterface
{
    /**
     * @var string
     */
    private $statement = null;

    /**
     * @param string $statement
     */
    public function __construct($statement)
    {
        $this->statement = $statement;
    }

    /**
     * {@inheritdoc}
     */
    public function sqlStatement(QueryCompiler $compiler = null)
    {
        return $this->statement;
    }

    /**
     * {@inheritdoc}
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
        return (object)['statement' => $this->sqlStatement()];
    }
}