<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Drivers\Postgres;

use Spiral\Database\Drivers\Postgres\PostgresDriver;
use Spiral\Database\Drivers\Postgres\QueryCompiler as PostgresCompiler;
use Spiral\Database\Entities\QueryCompiler;
use Spiral\Database\Exceptions\BuilderException;
use Spiral\Debug\Traits\LoggerTrait;

/**
 * Postgres driver requires little bit different way to handle last insert id.
 */
class InsertQuery extends \Spiral\Database\Builders\InsertQuery
{
    /**
     * Debug messages.
     */
    use LoggerTrait;

    /**
     * {@inheritdoc}
     */
    public function sqlStatement(QueryCompiler $compiler = null)
    {
        $driver = $this->database->driver();
        if (!$driver instanceof PostgresDriver || (!empty($compiler) && !$compiler instanceof PostgresCompiler)) {
            throw new BuilderException("Postgres InsertQuery can be used only with Postgres driver and compiler.");
        }

        if ($primary = $driver->getPrimary($this->database->getPrefix() . $this->table)) {
            $this->logger()->debug(
                "Primary key '{sequence}' automatically resolved for table '{table}'.", [
                'table'    => $this->table,
                'sequence' => $primary
            ]);
        }

        $compiler = !empty($compiler) ? $compiler : $this->compiler->reset();

        return $compiler->insert($this->table, $this->columns, $this->rowsets, $primary);
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