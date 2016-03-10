<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Atomizer;

use Spiral\Atomizer\Exceptions\AtomizerException;
use Spiral\Database\Entities\Schemas\AbstractTable;
use Spiral\Reactor\Body\Source;
use Spiral\Support\DFSSorter;

/**
 * Atomizer class used to create set of migration commands (declare migration) based on a given
 * table diffs including valid order and rollback operations.
 */
class Atomizer
{
    /**
     * @var AbstractTable[]
     */
    private $tables = [];

    /**
     * @var DeclaratorInterface
     */
    private $declarator = null;

    /**
     * @param array               $tables
     * @param DeclaratorInterface $declarator
     */
    public function __construct(array $tables, DeclaratorInterface $declarator)
    {
        foreach ($tables as $table) {
            if (!$table instanceof AbstractTable) {
                throw new AtomizerException("Atomizer can process only instances of 'AbstractTable'");
            }
        }

        $this->tables = $tables;
        $this->declarator = $declarator;
    }

    /**
     * Generate set of lines needed to describe migration (up command).
     *
     * @param Source $source
     */
    public function declareChanges(Source $source)
    {
        //todo: rename table

        foreach ($this->sortedTables() as $table) {
            if (!$table->comparator()->hasChanges()) {
                continue;
            }

            //New operations block
            $this->declareBlock($source);

            if (!$table->exists()) {
                $this->declarator->createTable($source, $table);
            } else {
                $this->declarator->updateTable($source, $table);
            }
        }
    }

    /**
     * Generate set of lines needed to rollback migration (down command).
     *
     * @param Source $source
     */
    public function revertChanges(Source $source)
    {
        foreach ($this->sortedTables(true) as $table) {
            if (!$table->comparator()->hasChanges()) {
                continue;
            }

            //New operations block
            $this->declareBlock($source);

            if (!$table->exists()) {
                $this->declarator->dropTable($source, $table);
            } else {
                $this->declarator->revertTable($source, $table);
            }
        }
    }

    /**
     * Tables sorted in order of their dependecies.
     *
     * @param bool $reverse
     * @return AbstractTable[]
     */
    protected function sortedTables($reverse = false)
    {
        /*
         * Tables has to be sorted using topological graph to execute operations in a valid order.
         */
        $sorter = new DFSSorter();
        foreach ($this->tables as $table) {
            $sorter->addItem($table->getName(), $table, $table->getDependencies());
        }

        $tables = $sorter->sort();

        if ($reverse) {
            return array_reverse($tables);
        }

        return $tables;
    }

    /**
     * Add spacing between commands, only if required.
     *
     * @param Source $source
     */
    private function declareBlock(Source $source)
    {
        if (!empty($source->getLines())) {
            $source->addLine("");
        }
    }
}