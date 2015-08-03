<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Interfaces\Builders;

use Spiral\Database\Interfaces\BuilderInterface;
use Spiral\Database\Interfaces\Builders\Common\JoinsBuilderInterface;
use Spiral\Database\Interfaces\Builders\Common\WhereBuilderInterface;
use Spiral\Pagination\PaginableInterface;

interface SelectBuilderInterface extends
    BuilderInterface,
    WhereBuilderInterface,
    JoinsBuilderInterface,
    PaginableInterface
{
    /**
     * Sort directions.
     */
    const SORT_ASC  = 'ASC';
    const SORT_DESC = 'DESC';

    /**
     * Mark query to return only distinct results.
     *
     * @return SelectBuilderInterface
     */
    public function distinct();

    /**
     * Sort result by column/expression. You can apply multiple sortings to query via calling method
     * few times or by specifying values using array of sort parameters:
     *
     * @param string $expression
     * @param string $direction Sorting direction, ASC|DESC.
     * @return SelectBuilderInterface
     */
    public function orderBy($expression, $direction = self::SORT_ASC);

    /**
     * Column or expression to group query by.
     *
     * @param string $expression
     * @return SelectBuilderInterface
     */
    public function groupBy($expression);

    //TODO: columns, union
}