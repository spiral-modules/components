<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Database\Drivers\SQLServer;

use Spiral\Database\Entities\QueryCompiler;

/**
 * Microsoft SQL server specific syntax compiler.
 */
class SQLServerCompiler extends QueryCompiler
{
    /**
     * {@inheritdoc}
     *
     * @link http://stackoverflow.com/questions/2135418/equivalent-of-limit-and-offset-for-sql-server
     */
    protected function compileLimit(int $limit, int $offset): string
    {
        if (empty($limit) && empty($offset)) {
            return '';
        }

        //Modern SQLServer are easier to work with
        $statement = "OFFSET {$offset} ROWS ";

        if (!empty($limit)) {
            $statement .= "FETCH NEXT {$limit} ROWS ONLY";
        }

        return trim($statement);
    }
}