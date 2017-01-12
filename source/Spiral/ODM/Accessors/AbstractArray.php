<?php
/**
 * Spiral Framework, Core Components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ODM\Accessors;

use Spiral\Models\Traits\SolidableTrait;
use Spiral\ODM\CompositableInterface;

/**
 * Provides ability to perform scalar operations on arrays.
 *
 * Attention, array will be saved as one big $set operation in case when multiple atomic
 * operations applied to it (not supported by Mongo).
 */
abstract class AbstractArray implements CompositableInterface, \Countable, \IteratorAggregate
{
    use SolidableTrait;

    /**
     * @var array
     */
    protected $values = [];

    /**
     * Low level atomic operations.
     *
     * @var array
     */
    protected $atomics = [];

    /**
     * @param mixed $values
     */
    public function __construct($values)
    {
        if (!is_array($values)) {
            //Since we have to overwrite non array field
            $this->solidState = true;
        }

        $this->addValues($values);
    }

    /**
     * Check if value presented in array.
     *
     * @param mixed $needle
     * @param bool  $strict
     *
     * @return bool
     */
    public function has($needle, bool $strict = true): bool
    {
        foreach ($this->values as $value) {
            if ($strict && $value === $needle) {
                return true;
            }

            if ($strict && $value == $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * Alias for atomic operation $push. Only values passed type filter will be added.
     *
     * @param mixed $value
     *
     * @return self|$this
     */
    public function push($value): AbstractArray
    {
        $value = $this->filterValue($value);
        if (is_null($value)) {
            return $this;
        }

        array_push($this->values, $value);
        $this->atomics['$push']['$each'][] = $value;

        return $this;
    }

    /**
     * Alias for atomic operation $addToSet. Only values passed type filter will be added.
     *
     * @param mixed $value
     *
     * @return self|$this
     */
    public function add($value): AbstractArray
    {
        $value = $this->filterValue($value);
        if (is_null($value)) {
            return $this;
        }

        if (!in_array($value, $this->values)) {
            array_push($this->values, $value);
        }

        $this->atomics['$addToSet']['$each'][] = $value;

        return $this;
    }

    /**
     * Alias for atomic operation $pull. Only values passed type filter will be added.
     *
     * @param mixed $value
     *
     * @return self|$this
     */
    public function pull($value): AbstractArray
    {
        $value = $this->filterValue($value);
        if (is_null($value)) {
            return $this;
        }

        //Removing values from array (non strict)
        $this->values = array_filter($this->values, function ($item) use ($value) {
            return $item != $value;
        });

        $this->atomics['$pull']['$in'][] = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function stateValue($data)
    {
        //Manually altered arrays must always end in solid state
        $this->solidState = true;

        //Flushing existed values
        $this->values = [];

        //Pushing filtered values in array
        $this->addValues($data);
    }

    /**
     * {@inheritdoc}
     */
    public function hasUpdates(): bool
    {
        return !empty($this->atomics);
    }

    /**
     * {@inheritdoc}
     */
    public function flushUpdates()
    {
        $this->atomics = [];
    }

    /**
     * {@inheritdoc}
     */
    public function buildAtomics(string $container = ''): array
    {
        if (!$this->hasUpdates()) {
            return [];
        }

        //Mongo does not support multiple operations for one field, switching to $set (make sure it's
        //reasonable)
        if ($this->solidState || count($this->atomics) > 1) {
            //We don't care about atomics in solid state
            return ['$set' => [$container => $this->packValue()]];
        }

        $atomics = [];
        foreach ($this->atomics as $operation => $values) {
            $atomics[$operation] = [$container => $values];
        }

        return $atomics;
    }

    /**
     * @return array
     */
    public function packValue(): array
    {
        return array_values($this->values);
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return count($this->values);
    }

    /**
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->values);
    }

    /**
     * Clone accessor and ensure that it state is updated.
     */
    public function __clone()
    {
        $this->solidState = true;
        $this->atomics = [];
    }

    /**
     * @return array
     */
    public function __debugInfo()
    {
        return [
            'values'  => $this->packValue(),
            'atomics' => $this->buildAtomics('@scalarArray'),
        ];
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->packValue();
    }

    /**
     * Add values matched with filter.
     *
     * @param mixed $values
     */
    protected function addValues($values)
    {
        if (!is_array($values) && !$values instanceof \Traversable) {
            //Unable to process values
            return;
        }

        foreach ($values as $value) {
            //Passing every value thought the filter
            $value = $this->filterValue($value);
            if (!is_null($value)) {
                $this->values[] = $value;
            }
        }
    }

    /**
     * Filter value, MUST return null if value is invalid.
     *
     * @param mixed $value
     *
     * @return mixed|null
     */
    abstract protected function filterValue($value);
}