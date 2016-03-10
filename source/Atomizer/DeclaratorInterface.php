<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Atomizer;

use Spiral\Database\Entities\Schemas\AbstractTable;
use Spiral\Reactor\Body\Source;

/**
 * Abstraction which provides bridge between atomizer and set of migration operations and actual
 * migrations code. Ideally i need adapter for Phinx one day.
 */
interface DeclaratorInterface
{
    /**
     * Migration engine specific table creation syntax.
     *
     * @param Source           $source
     * @param AbstractTable    $table
     */
    public function createTable(Source $source, AbstractTable $table);

    /**
     * Migration engine specific table update syntax.
     *
     * @param Source           $source
     * @param AbstractTable    $table
     */
    public function updateTable(Source $source, AbstractTable $table);

    /**
     * Migration engine specific table revert syntax.
     *
     * @param Source           $source
     * @param AbstractTable    $table
     */
    public function revertTable(Source $source, AbstractTable $table);

    /**
     * Migration engine specific table drop syntax.
     *
     * @param Source           $source
     * @param AbstractTable    $table
     */
    public function dropTable(Source $source, AbstractTable $table);
}