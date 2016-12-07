<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Drivers\MySQL;

use Spiral\Database\Entities\QueryCompiler as AbstractCompiler;
use Spiral\Database\Injections\ParameterInterface;

/**
 * MySQL syntax specific compiler.
 */
class QueryCompiler extends AbstractCompiler
{
    /**
     * {@inheritdoc}
     */
    public function orderParameters(
        int $queryType,
        array $whereParameters = [],
        array $onParameters = [],
        array $havingParameters = [],
        array $columnIdentifiers = []
    ): array {
        if ($queryType == self::UPDATE_QUERY) {
            //Where statement has pretty specific order
            return array_merge($onParameters, $columnIdentifiers, $whereParameters);
        }

        return parent::orderParameters(
            $queryType,
            $whereParameters,
            $onParameters,
            $havingParameters,
            $columnIdentifiers
        );
    }

    /**
     * {@inheritdoc}
     *
     * @link http://dev.mysql.com/doc/refman/5.0/en/select.html#id4651990
     */
    protected function compileLimit(int $limit, int $offset): string
    {
        if (empty($limit) && empty($offset)) {
            return '';
        }

        $statement = '';
        if (!empty($limit) || !empty($offset)) {
            //When limit is not provided but offset does we can replace limit value with PHP_INT_MAX
            $statement = 'LIMIT ' . ($limit ?: '18446744073709551615') . ' ';
        }

        if (!empty($offset)) {
            $statement .= "OFFSET {$offset}";
        }

        return trim($statement);
    }

    /**
     * Resolve operator value based on value value. ;).
     *
     * @param mixed  $parameter
     * @param string $operator
     *
     * @return string
     */
    protected function prepareOperator($parameter, string $operator): string
    {
        if (!$parameter instanceof ParameterInterface) {
            //Probably fragment
            return $operator;
        }

        if ($parameter->getType() == \PDO::PARAM_NULL) {
            switch ($operator) {
                case '=':
                    return 'IS';
                case '!=':
                    return 'IS NOT';
            }
        }

        return parent::prepareOperator($parameter, $operator);
    }
}
