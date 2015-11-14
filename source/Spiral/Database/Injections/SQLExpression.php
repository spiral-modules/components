<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Database\Injections;

use Spiral\Database\Entities\QueryCompiler;

/**
 * SQLExpression provides ability to mock part of SQL code responsible for operations involving
 * table and column names. This class will quote and prefix every found table name and column while
 * query compilation.
 *
 * Example: new SQLExpression("table.column = table.column + 1");
 *
 * I potentially should have an interface for such class.
 */
class SQLExpression extends Fragment
{
    /**
     * {@inheritdoc}
     */
    public function sqlStatement(QueryCompiler $compiler = null)
    {
        if (empty($compiler)) {
            //We might need to throw an exception here in some cases
            return parent::sqlStatement();
        }

        return $compiler->quote(parent::sqlStatement($compiler));
    }
}