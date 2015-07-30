<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Drivers\Postgres\Builders;

use Spiral\Database\Builders\InsertQuery as BaseInsertQuery;
use Spiral\Database\DatabaseException;
use Spiral\Database\Drivers\Postgres\PostgresDriver;
use Spiral\Database\QueryCompiler;
use Spiral\Debug\Traits\LoggerTrait;

class InsertQuery extends BaseInsertQuery
{
    /**
     * Logging.
     */
    use LoggerTrait;

    /**
     * Get or render SQL statement.
     *
     * @param QueryCompiler $compiler
     * @return string
     */
    public function sqlStatement(QueryCompiler $compiler = null)
    {
        $driver = $this->database->getDriver();
        if (!$driver instanceof PostgresDriver)
        {
            throw new DatabaseException("Postgres InsertQuery can be used only with Postgres driver.");
        }

        if ($primary = $driver->getPrimary($this->database->getPrefix() . $this->table))
        {
            $this->logger()->debug(
                "Primary key '{sequence}' automatically resolved for table '{table}'.", [
                'table'    => $this->table,
                'sequence' => $primary
            ]);
        }

        $compiler = !empty($compiler) ? $compiler : $this->compiler;

        return $compiler->insert($this->table, $this->columns, $this->values, $primary);
    }

    /**
     * Run QueryBuilder statement against parent database. Method will return lastInsertID value.
     *
     * @return mixed
     */
    public function run()
    {
        return (int)$this->database->statement(
            $this->sqlStatement(),
            $this->getParameters()
        )->fetchColumn();
    }
}