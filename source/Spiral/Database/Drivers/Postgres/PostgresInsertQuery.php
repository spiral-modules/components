<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Drivers\Postgres;

use Spiral\Database\Builders\InsertQuery;
use Spiral\Database\Drivers\Postgres\QueryCompiler as PostgresCompiler;
use Spiral\Database\Entities\QueryCompiler as AbstractCompiler;
use Spiral\Database\Exceptions\BuilderException;
use Spiral\Debug\Traits\LoggerTrait;

/**
 * Postgres driver requires little bit different way to handle last insert id.
 */
class PostgresInsertQuery extends InsertQuery
{
    use LoggerTrait;

    /**
     * {@inheritdoc}
     */
    public function sqlStatement(AbstractCompiler $compiler = null)
    {
        $driver = $this->database->driver();

        if (
            !$driver instanceof PostgresDriver
            || (!empty($compiler) && !$compiler instanceof PostgresCompiler)
        ) {
            throw new BuilderException(
                'Postgres InsertQuery can be used only with Postgres driver and compiler.'
            );
        }

        if ($primary = $driver->getPrimary($this->database->getPrefix() . $this->table)) {
            $this->logger()->debug(
                "Primary key '{sequence}' automatically resolved for table '{table}'.",
                [
                    'table'    => $this->table,
                    'sequence' => $primary,
                ]
            );
        }

        if (empty($compiler)) {
            $compiler = $this->compiler->resetQuoter();
        }

        return $compiler->compileInsert($this->table, $this->columns, $this->rowsets, $primary);
    }

    /**
     * {@inheritdoc}
     */
    public function run()
    {
        return (int)$this->database->statement(
            $this->sqlStatement(), $this->getParameters()
        )->fetchColumn();
    }
}
