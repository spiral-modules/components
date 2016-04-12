<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Database\Query;

use Spiral\Database\Exceptions\ResultException;
use Spiral\Database\ResultInterface;

/**
 * Represents simple array.
 */
class ArrayResult extends \ArrayIterator implements ResultInterface
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
    public function __construct(array  $rowsets)
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
    public function countColumns()
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

        foreach ($this->bindings as $name => &$variable) {
            $variable = $values[$name];
        }

        return $values;
    }

    /**
     * Bind a column value to a PHP variable.
     *
     * @param int|string $fieldID Column number (0 - first column)
     * @param mixed      $variable
     * @return self
     */
    public function bind($fieldID, &$variable)
    {
        if ($this->count() == 0) {
            return $this;
        }

        if (is_numeric($fieldID)) {
            //Getting column number
            foreach ($this->columns as $name => $index) {
                if ($index == $fieldID - 1) {
                    $this->bindings[$name] = &$variable;

                    return $this;
                }
            }

            throw new ResultException("No such column #{$fieldID}");
        } else {
            if (!isset($this->columns[$fieldID])) {
                throw new ResultException("No such column '{$fieldID}'");
            }

            $this->bindings[$fieldID] = &$variable;
        }

        return $this;
    }

    /**
     * Returns a single column value from the next row of a result set.
     *
     * @param int $fieldID Column number (0 - first column)
     *
     * @return mixed
     */
    public function fetchColumn($fieldID = 0)
    {
        $values = $this->fetch();

        if ($values === false) {
            return null;
        }

        if (is_numeric($fieldID)) {
            if ($this->countColumns() > $fieldID) {
                throw new ResultException("No such column #{$fieldID}");
            }

            return array_values($values)[$fieldID];
        }

        if (!isset($this->columns[$fieldID])) {
            throw new ResultException("No such column '{$fieldID}'");
        }

        return $values[$fieldID];
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll()
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