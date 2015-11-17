<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Entities;

use Spiral\Core\Component;
use Spiral\Database\Entities\Schemas\AbstractTable;

/**
 * Saves multiple linked tables at once but treating their cross dependency.
 */
class SynchronizationBus extends Component
{
    /**
     * @var AbstractTable[]
     */
    protected $tables = [];

    /**
     * @param AbstractTable[] $tables
     */
    public function __construct(array $tables)
    {
        $this->tables = $tables;
    }

    /**
     * @return AbstractTable[]
     */
    public function getTables()
    {
        return $this->tables;
    }

    /**
     * List of tables sorted in order of cross dependency.
     *
     * @return AbstractTable[]
     */
    public function sortedTables()
    {
        $tables = $this->tables;
        uasort($tables, function (AbstractTable $tableA, AbstractTable $tableB) {
            if (in_array($tableA->getName(), $tableB->getDependencies())) {
                return true;
            }

            return count($tableB->getDependencies()) > count($tableA->getDependencies());
        });

        return array_reverse($tables);
    }

    /**
     * Syncronize table schemas.
     */
    public function syncronize()
    {
        //Dropping non declared foreign keys
        $this->saveTables(false, false, true);

        //Dropping non declared indexes
        $this->saveTables(false, true, true);

        //Dropping non declared columns
        $this->saveTables(true, true, true);
    }

    /**
     * @param bool $forgetColumns
     * @param bool $forgetIndexes
     * @param bool $forgetForeigns
     */
    protected function saveTables($forgetColumns, $forgetIndexes, $forgetForeigns)
    {
        foreach ($this->sortedTables() as $table) {
            $table->save($forgetColumns, $forgetIndexes, $forgetForeigns);
        }
    }
}