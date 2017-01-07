<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Helpers;

use Psr\Log\LoggerInterface;
use Spiral\Core\Component;
use Spiral\Database\Entities\AbstractHandler as Behaviour;
use Spiral\Database\Entities\Driver;
use Spiral\Database\Schemas\Prototypes\AbstractTable;
use Spiral\Debug\Traits\LoggerTrait;
use Spiral\Support\DFSSorter;

/**
 * Saves multiple linked tables at once but treating their cross dependency.
 *
 * Attention, not every database support transactional schema manipulations!
 */
class SynchronizationPool extends Component
{
    use LoggerTrait;

    /**
     * @var AbstractTable[]
     */
    private $tables = [];

    /**
     * @var Driver[]
     */
    private $drivers = [];

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
    public function sortedTables(): array
    {
        /*
         * Tables has to be sorted using topological graph to execute operations in a valid order.
         */
        $sorter = new DFSSorter();
        foreach ($this->tables as $table) {
            $sorter->addItem($table->getName(), $table, $table->getDependencies());
        }

        return $sorter->sort();
    }

    /**
     * Synchronize tables.
     *
     * @param LoggerInterface|null $logger
     *
     * @throws \Exception
     * @throws \Throwable
     */
    public function run(LoggerInterface $logger = null)
    {
        $this->beginTransaction();

        try {
            //Drop not-needed foreign keys and alter everything else
            foreach ($this->sortedTables() as $table) {
                if ($table->exists()) {
                    $table->save(Behaviour::DROP_FOREIGNS, $logger, false);
                }
            }

            //Drop not-needed indexes
            foreach ($this->sortedTables() as $table) {
                if ($table->exists()) {
                    $table->save(Behaviour::DROP_INDEXES, $logger, false);
                }
            }

            //Other changes [NEW TABLES WILL BE CREATED HERE!]
            foreach ($this->sortedTables() as $table) {
                $table->save(
                    Behaviour::DO_ALL ^ Behaviour::DROP_FOREIGNS ^ Behaviour::DROP_INDEXES ^ Behaviour::CREATE_FOREIGNS,
                    $logger
                );
            }

            //Finishing with new foreign keys
            foreach ($this->sortedTables() as $table) {
                $table->save(Behaviour::CREATE_FOREIGNS, $logger, true);
            }
        } catch (\Throwable $e) {
            $this->rollbackTransaction();
            throw $e;
        }

        $this->commitTransaction();
    }

    /**
     * Begin mass transaction.
     */
    protected function beginTransaction()
    {
        $this->logger()->debug('Begin transaction');

        foreach ($this->drivers as $driver) {
            $driver->beginTransaction();
        }
    }

    /**
     * Commit mass transaction.
     */
    protected function commitTransaction()
    {
        $this->logger()->debug('Commit transaction');

        foreach ($this->drivers as $driver) {
            $driver->commitTransaction();
        }
    }

    /**
     * Roll back mass transaction.
     */
    protected function rollbackTransaction()
    {
        $this->logger()->warning('Roll back transaction');

        foreach ($this->drivers as $driver) {
            $driver->rollbackTransaction();
        }
    }

    /**
     * Collecting all involved drivers.
     */
    private function collectDrivers()
    {
        foreach ($this->tables as $table) {
            if (!in_array($table->getDriver(), $this->drivers, true)) {
                $this->drivers[] = $table->getDriver();
            }
        }
    }
}
