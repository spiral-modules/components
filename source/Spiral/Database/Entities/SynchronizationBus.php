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
use Spiral\Debug\Traits\LoggerTrait;
use Spiral\Sorters\DFSSorter;

/**
 * Saves multiple linked tables at once but treating their cross dependency.
 */
class SynchronizationBus extends Component
{
    /**
     * Logging.
     */
    use LoggerTrait;

    /**
     * @var AbstractTable[]
     */
    protected $tables = [];

    /**
     * @var Driver[]
     */
    protected $drivers = [];

    /**
     * @param AbstractTable[] $tables
     */
    public function __construct(array $tables)
    {
        $this->tables = $tables;
        $this->collectDrivers();
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
        $sorter = new DFSSorter();

        foreach ($tables as $table) {
            $sorter->addItemObject($table->getName(), $table, $table->getDependencies());
        }

        return $sorter->sort();
    }

    /**
     * Syncronize table schemas.
     *
     * @throws \Exception
     */
    public function syncronize()
    {
        $this->beginTransaction();

        try {
            //Dropping non declared foreign keys
            $this->saveTables(false, false, true);

            //Dropping non declared indexes
            $this->saveTables(false, true, true);

            //Dropping non declared columns
            $this->saveTables(true, true, true);
        } catch (\Exception $exception) {
            $this->rollbackTransaction();
            throw $exception;
        }

        $this->commitTransaction();
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

    /**
     * Collecting all involved drivers.
     */
    protected function collectDrivers()
    {
        foreach ($this->tables as $table) {
            if (!in_array($table->driver(), $this->drivers, true)) {
                $this->drivers[] = $table->driver();
            }
        }
    }

    /**
     * Begin mass transaction.
     */
    protected function beginTransaction()
    {
        $this->logger()->debug("Begin transaction");
        foreach ($this->drivers as $driver) {
            $driver->beginTransaction();
        }
    }

    /**
     * Commit mass transaction.
     */
    protected function commitTransaction()
    {
        $this->logger()->debug("Commit transaction");
        foreach ($this->drivers as $driver) {
            $driver->commitTransaction();
        }
    }

    /**
     * Roll back mass transaction.
     */
    protected function rollbackTransaction()
    {
        $this->logger()->warning("Roll back transaction");
        foreach ($this->drivers as $driver) {
            $driver->rollbackTransaction();
        }
    }
}