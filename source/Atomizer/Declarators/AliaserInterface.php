<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Atomizer\Declarators;

use Spiral\Database\Entities\Schemas\AbstractTable;

/**
 * Provides environment specific table and database aliases for AbstractTable.
 */
interface AliaserInterface
{
    /**
     * @param AbstractTable $table
     * @return string
     */
    public function getTable(AbstractTable $table);

    /**
     * @param AbstractTable $table
     * @return string
     */
    public function getDatabase(AbstractTable $table);
}