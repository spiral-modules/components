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
 */
class SynchronizationBus extends Component
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
            $this->runChanges(Behaviour::DROP_FOREIGNS, $logger);

            //Drop not-needed indexes
            $this->runChanges(Behaviour::DROP_INDEXES, $logger);

            //Other changes
            $this->runChanges(
                Behaviour::DO_ALL ^ Behaviour::DROP_FOREIGNS ^ Behaviour::DROP_INDEXES,
                $logger,
                true
            );

        } catch (\Exception $e) {
            $this->rollbackTransaction();
            throw $e;
        } catch (\Throwable $e) {
            $this->rollbackTransaction();
            throw $e;
        }

        $this->commitTransaction();
    }

    /**
     * Rum all tables.
     *
     * @param int                  $behaviour
     * @param LoggerInterface|null $logger
     * @param bool                 $reset Reset schemas.
     */
    protected function runChanges(
        int $behaviour = Behaviour::DO_ALL,
        LoggerInterface $logger = null,
        bool $reset = false
    ) {
        foreach ($this->sortedTables() as $table) {
            $table->save($behaviour, $logger, $reset);
        }
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
