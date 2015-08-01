<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Builders;

use Psr\Log\LoggerAwareInterface;
use Spiral\Database\Builders\Traits\JoinsTrait;
use Spiral\Database\Database;
use Spiral\Database\QueryCompiler;
use Spiral\Debug\Traits\LoggerTrait;

abstract class AbstractAffect extends AbstractWhere implements LoggerAwareInterface
{
    /**
     * A lot of traits.
     */
    use LoggerTrait, JoinsTrait;

    /**
     * Table name to affect data into, should not include postfix. Setter method is not provided as
     * it can be named differently in different builders.
     *
     * @var string
     */
    protected $table = '';

    /**
     * AffectQuery is query builder used to compile affection (delete, update) queries for one
     * associated table.
     *
     * @param Database      $database Parent database.
     * @param QueryCompiler $compiler Driver specific QueryGrammar instance (one per builder).
     * @param string        $table    Associated table name.
     * @param array         $where    Initial set of where rules specified as array.
     */
    public function __construct(
        Database $database,
        QueryCompiler $compiler,
        $table = '',
        array $where = []
    )
    {
        parent::__construct($database, $compiler);

        $this->table = $table;
        !empty($where) && $this->where($where);
    }

    /**
     * Run QueryBuilder statement against parent database. Method will be overloaded by child builder
     * to return correct value. Affect query builder will return count affected rows.
     *
     * @return int
     */
    public function run()
    {
        if (empty($this->whereTokens))
        {
            $this->logger()->warning(
                "Affect query performed without any condition or search limitation, "
                . "whole table will be updated."
            );
        }

        return parent::run()->rowCount();
    }
}