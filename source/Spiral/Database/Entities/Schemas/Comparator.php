<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Database\Entities\Schemas;

use Spiral\Core\Component;

/**
 * Compares two table states.
 */
class Comparator extends Component
{
    /**
     * @var TableState
     */
    private $initial = null;

    /**
     * @var TableState
     */
    private $current = null;

    /**
     * @param TableState $initial
     * @param TableState $current
     */
    public function __construct(TableState $initial, TableState $current)
    {
        $this->initial = $initial;
        $this->current = $current;
    }

    /**
     * @return bool
     */
    public function hasChanges()
    {
        if ($this->current->getName() != $this->initial->getName()) {
            return true;
        }

        if ($this->current->getPrimaryKeys() != $this->initial->getPrimaryKeys()) {
            return true;
        }

        $difference = [
            count($this->addedColumns()),
            count($this->droppedColumns()),
            count($this->alteredColumns()),
            count($this->addedIndexes()),
            count($this->droppedIndexes()),
            count($this->alteredIndexes()),
            count($this->addedForeigns()),
            count($this->droppedForeigns()),
            count($this->alteredForeigns())
        ];

        return array_sum($difference) != 0;
    }

    /**
     * @return AbstractColumn[]
     */
    public function addedColumns()
    {
        $difference = [];
        foreach ($this->current->getColumns() as $name => $column) {
            if (!$this->initial->knowsColumn($name)) {
                $difference[] = $column;
            }
        }

        return $difference;
    }

    /**
     * @return AbstractColumn[]
     */
    public function droppedColumns()
    {
        $difference = [];
        foreach ($this->initial->getColumns() as $name => $column) {
            if (!$this->current->knowsColumn($name)) {
                $difference[] = $column;
            }
        }

        return $difference;
    }

    /**
     * Returns array where each value contain current and initial element state.
     *
     * @return array
     */
    public function alteredColumns()
    {
        $difference = [];

        $initialColumns = $this->initial->getColumns();
        foreach ($this->current->getColumns() as $name => $column) {
            if (!$this->initial->knowsColumn($name)) {
                //Added into schema
                continue;
            }

            if (!$column->compare($initialColumns[$name])) {
                $difference[] = [$column, $initialColumns[$name]];
            }
        }

        return $difference;
    }

    /**
     * @return AbstractIndex[]
     */
    public function addedIndexes()
    {
        $difference = [];
        foreach ($this->current->getIndexes() as $name => $index) {
            if (!$this->initial->knowsIndex($name)) {
                $difference[] = $index;
            }
        }

        return $difference;
    }

    /**
     * @return AbstractIndex[]
     */
    public function droppedIndexes()
    {
        $difference = [];
        foreach ($this->initial->getIndexes() as $name => $index) {
            if (!$this->current->knowsIndex($name)) {
                $difference[] = $index;
            }
        }

        return $difference;
    }


    /**
     * Returns array where each value contain current and initial element state.
     *
     * @return array
     */
    public function alteredIndexes()
    {
        $difference = [];

        $initialIndexes = $this->initial->getIndexes();
        foreach ($this->current->getIndexes() as $name => $index) {
            if (!$this->initial->knowsIndex($name)) {
                //Added into schema
                continue;
            }

            if (!$index->compare($initialIndexes[$name])) {
                $difference[] = [$index, $initialIndexes[$name]];
            }
        }

        return $difference;
    }

    /**
     * @return AbstractReference[]
     */
    public function addedForeigns()
    {
        $difference = [];
        foreach ($this->current->getForeigns() as $name => $foreign) {
            if (!$this->initial->knowsForeign($name)) {
                $difference[] = $foreign;
            }
        }

        return $difference;
    }

    /**
     * @return AbstractReference[]
     */
    public function droppedForeigns()
    {
        $difference = [];
        foreach ($this->initial->getForeigns() as $name => $foreign) {
            if (!$this->current->knowsForeign($name)) {
                $difference[] = $foreign;
            }
        }

        return $difference;
    }

    /**
     * Returns array where each value contain current and initial element state.
     *
     * @return array
     */
    public function alteredForeigns()
    {
        $difference = [];

        $initialForeigns = $this->initial->getForeigns();
        foreach ($this->current->getForeigns() as $name => $foreign) {
            if (!$this->initial->knowsForeign($name)) {
                //Added into schema
                continue;
            }

            if (!$foreign->compare($initialForeigns[$name])) {
                $difference[] = [$foreign, $initialForeigns[$name]];
            }
        }

        return $difference;
    }

    /**
     * @return object
     */
    public function __debugInfo()
    {
        return (object)[
            'name'        => [
                'initial' => $this->initial->getName(),
                'current' => $this->current->getName()
            ],
            'columns'     => [
                'added'   => $this->addedColumns(),
                'dropped' => $this->droppedColumns(),
                'altered' => $this->alteredColumns(),
            ],
            'indexes'     => [
                'added'   => $this->addedIndexes(),
                'dropped' => $this->droppedIndexes(),
                'altered' => $this->alteredIndexes(),
            ],
            'foreignKeys' => [
                'added'   => $this->addedForeigns(),
                'dropped' => $this->droppedForeigns(),
                'altered' => $this->alteredForeigns()
            ]
        ];
    }
}