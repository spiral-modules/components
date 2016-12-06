<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\ORM\Accessors;

use Spiral\Database\Entities\Driver;
use Spiral\Database\Injections\Expression;
use Spiral\Models\EntityInterface;
use Spiral\ORM\RecordAccessorInterface;

/**
 * Atomic number accessor provides ability to change numeric record field using delta values, this
 * accessor is very similar by idea to Document->inc() method.
 *
 * Accessor will declare expression to sent to update statement in compileUpdate() method. If parent
 * record is solid (solid state) dynamic expression will be ignored and accessor will return it's
 * internal numeric value (altered by inc/dec operations and based on original record value).
 */
class AtomicNumber implements RecordAccessorInterface
{
    /**
     * Current numeric value.
     *
     * @var float|int
     */
    private $value = null;

    /**
     * @var float|int
     */
    private $original = null;

    /**
     * Difference between original and current values.
     *
     * @var float|int
     */
    protected $delta = 0;

    /**
     * {@inheritdoc}
     */
    public function __construct($number)
    {
        $this->original = $this->value = $number;
    }

    /**
     * {@inheritdoc}
     */
    public function setValue($data)
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
        if ($this->delta === 0) {
            //Nothing were changed
            return $this->value;
        }

        $sign = $this->delta > 0 ? '+' : '-';

        //"field" = "field" + delta
        return new Expression("{$field} {$sign} " . abs($this->delta));
    }

    /**
     * Increment numeric value (alias for inc) by given delta.
     *
     * @param float|int $delta
     *
     * @return $this
     */
    public function inc($delta = 1)
    {
        $this->value += $delta;
        $this->delta += $delta;

        return $this;
    }

    /**
     * Increment numeric value (alias for inc) by given delta.
     *
     * @param float|int $delta
     *
     * @return $this
     */
    public function add($delta = 1)
    {
        $this->value += $delta;
        $this->delta += $delta;

        return $this;
    }

    /**
     * Decrement numeric value by given delta.
     *
     * @param float|int $delta Delta must be positive to deduct value.
     *
     * @return $this
     */
    public function dec($delta = 1)
    {
        $this->value -= $delta;
        $this->delta -= $delta;

        return $this;
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
     * @return string
     */
    public function __toString()
    {
        return (string)$this->value;
    }

    /**
     * @return array
     */
    public function __debugInfo()
    {
        return [
            'value' => $this->value,
            'delta' => $this->delta,
        ];
    }
}
