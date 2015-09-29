<?php
/**
 * Spiral Framework, SpiralScout LLC.
 *
 * @package   spiralFramework
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2011
 */
namespace Spiral\ODM\Traits;

/**
 * Set of simple atomic operations for DocumentEntity.
 */
trait AtomicsTrait
{
    /**
     * Alias for atomic operation $set. Attention, this operation is not identical to setField()
     * method, it performs low level operation and can be used only on simple fields. No filters
     * will be applied to field!
     *
     * @param string $field
     * @param mixed  $value
     * @return $this
     * @throws DocumentException
     */
    public function set($field, $value)
    {
        if ($this->hasUpdates($field, true)) {
            throw new DocumentException(
                "Unable to apply multiple atomic operation to field '{$field}'."
            );
        }

        $this->atomics[self::ATOMIC_SET][$field] = $value;
        $this->fields[$field] = $value;

        return $this;
    }

    /**
     * Alias for atomic operation $inc.
     *
     * @param string $field
     * @param string $value
     * @return $this
     * @throws DocumentException
     */
    public function inc($field, $value)
    {
        if ($this->hasUpdates($field, true) && !isset($this->atomics['$inc'][$field])) {
            throw new DocumentException(
                "Unable to apply multiple atomic operation to field '{$field}'."
            );
        }

        if (!isset($this->atomics['$inc'][$field])) {
            $this->atomics['$inc'][$field] = 0;
        }

        $this->atomics['$inc'][$field] += $value;
        $this->fields[$field] += $value;

        return $this;
    }
} 