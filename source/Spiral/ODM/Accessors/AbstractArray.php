<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ODM\Accessors;

use Spiral\Models\Traits\SolidStateTrait;
use Spiral\ODM\CompositableInterface;

/**
 * Provides ability to perform scalar operations on arrays.
 */
class AbstractArray implements CompositableInterface, \Countable
{
    use SolidStateTrait;

    /**
     * @var array
     */
    protected $values = [];

    /**
     * Indication that values were updated.
     *
     * @var bool
     */
    protected $updated = false;

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
        $this->setValue($values);
    }

    /**
     * {@inheritdoc}
     */
    public function setValue($data)
    {
        // TODO: Implement setValue() method.
    }

    /**
     * {@inheritdoc}
     */
    public function hasUpdates(): bool
    {
        return $this->updated;
    }

    /**
     * {@inheritdoc}
     */
    public function buildAtomics(string $container = ''): array
    {
        // TODO: Implement buildAtomics() method.
    }

    /**
     * {@inheritdoc}
     */
    public function flushUpdates()
    {
        // TODO: Implement flushUpdates() method.
    }

    /**
     * @return array
     */
    public function packValue()
    {
        return $this->values;
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return count($this->values);
    }

    /**
     * Clone accessor and ensure that it state is updated.
     */
    public function __clone()
    {
        //Every cloned accessor must become solid and updated
        $this->solidState = true;
        $this->updated = true;
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
}