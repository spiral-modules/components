<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Database\Entities\Query;

use Spiral\Database\Exceptions\ResultException;

/**
 * Represents simple array.
 */
class ArrayResult extends \ArrayIterator
{
    /**
     * @var array
     */
    private $columns = [];

    /**
     * Column = variable binding.
     *
     * @var array
     */
    private $bindings = [];

    /**
     * @param array $rowsets
     */
    public function __construct(array $rowsets)
    {
        parent::__construct($rowsets);

        if (count($rowsets) > 1) {
            /*
             * {
             *    columnA: indexA,
             *    columnB: indexB
             * }
             */
            $this->columns = array_flip(array_keys(current($rowsets)));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function countColumns(): int
    {
        return count($this->columns);
    }

    /**
     * {@inheritdoc}
     */
    public function fetch()
    {
        $this->next();
        $values = $this->current();

        //Filling bindings
        foreach ($this->bindings as $name => &$variable) {
            $variable = $values[$name];
        }

        return $values;
    }

    /**
     * Bind a column value to a PHP variable.
     *
     * @param int|string $columnID Column number (0 - first column)
     * @param mixed      $variable
     *
     * @return self|$this
     *
     * @throws ResultException
     */
    public function bind($columnID, &$variable): ArrayResult
    {
        if ($this->count() == 0) {
            return $this;
        }

        if (is_numeric($columnID)) {
            //Getting column number
            foreach ($this->columns as $name => $index) {
                if ($index == $columnID - 1) {
                    $this->bindings[$name] = &$variable;

                    return $this;
                }
            }

            throw new ResultException("No such column #{$columnID}");
        } else {
            if (!isset($this->columns[$columnID])) {
                throw new ResultException("No such column '{$columnID}'");
            }

            $this->bindings[$columnID] = &$variable;
        }

        return $this;
    }

    /**
     * Returns a single column value from the next row of a result set.
     *
     * @param int $columnID Column number (0 - first column)
     *
     * @return mixed
     */
    public function fetchColumn($columnID = 0)
    {
        $values = $this->fetch();

        if ($values === false) {
            return null;
        }

        if (is_numeric($columnID)) {
            if ($this->countColumns() > $columnID) {
                throw new ResultException("No such column #{$columnID}");
            }

            return array_values($values)[$columnID];
        }

        if (!isset($this->columns[$columnID])) {
            throw new ResultException("No such column '{$columnID}'");
        }

        return $values[$columnID];
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return $this->fetchAll();
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll(): array
    {
        $result = [];
        while (($values = $this->fetch()) !== false) {
            //This loop is required to make sure that bindings are set
            $result[] = $values;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        //Nothing to do
    }
}