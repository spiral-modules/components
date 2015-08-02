<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM\Accessors;

use Spiral\Database\Driver;
use Spiral\Database\SqlExpression;
use Spiral\ORM\Model;
use Spiral\ORM\ModelAccessorInterface;

class AtomicNumber implements ModelAccessorInterface
{
    /**
     * Parent active record.
     *
     * @var Model
     */
    protected $parent = null;

    /**
     * Original value.
     *
     * @var float|int
     */
    protected $original = null;

    /**
     * Numeric value.
     *
     * @var float|int
     */
    protected $value = null;

    /**
     * Current value change.
     *
     * @var float|int
     */
    protected $delta = 0;

    /**
     * {@inheritdoc}
     */
    public function __construct($data = null, $parent = null, $options = null)
    {
        $this->original = $this->value = $data;
        $this->parent = $parent;
    }

    /**
     * {@inheritdoc}
     */
    public function embed($parent)
    {
        $accessor = clone $this;
        $accessor->parent = $parent;

        return $accessor;
    }

    /**
     * {@inheritdoc}
     */
    public function setData($data)
    {
        $this->original = $this->value = $data;
        $this->delta = 0;
    }

    /**
     * {@inheritdoc}
     */
    public function serializeData()
    {
        return $this->value;
    }

    /**
     * {@inheritdoc}
     */
    public function hasUpdates()
    {
        return $this->value !== $this->original;
    }

    /**
     * {@inheritdoc}
     */
    public function flushUpdates()
    {
        $this->original = $this->value;
        $this->delta = 0;
    }

    /**
     * {@inheritdoc}
     */
    public function compileUpdates($field = '')
    {
        if ($this->delta === 0)
        {
            return $this->value;
        }

        $sign = $this->delta > 0 ? '+' : '-';

        return new SqlExpression("{$field} {$sign} " . abs($this->delta));
    }

    /**
     * {@inheritdoc}
     */
    public function defaultValue(Driver $driver)
    {
        return $this->serializeData();
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return $this->serializeData();
    }

    /**
     * Increment numeric value (alias for inc).
     *
     * @param float|int $delta
     * @return $this
     */
    public function inc($delta = 1)
    {
        $this->value += $delta;
        $this->delta += $delta;

        return $this;
    }

    /**
     * Increment numeric value (alias for inc).
     *
     * @param float|int $delta
     * @return $this
     */
    public function add($delta = 1)
    {
        $this->value += $delta;
        $this->delta += $delta;

        return $this;
    }

    /**
     * Decrement numeric value.
     *
     * @param float|int $delta
     * @return $this
     */
    public function dec($delta = 1)
    {
        $this->value -= $delta;
        $this->delta -= $delta;

        return $this;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return (string)$this->value;
    }

    /**
     * @return object
     */
    public function __debugInfo()
    {
        return (object)[
            'value' => $this->value,
            'delta' => $this->delta
        ];
    }
}