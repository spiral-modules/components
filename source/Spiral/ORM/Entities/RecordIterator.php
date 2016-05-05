<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\ORM\Entities;

use Spiral\ORM\ORMInterface;
use Spiral\ORM\RecordInterface;

/**
 * Provides iteration over set of specified records data using internal instances cache. Does not
 * implements classic "collection" methods besides 'has'.
 */
class RecordIterator implements \IteratorAggregate, \Countable, \JsonSerializable
{
    /**
     * @var RecordInterface[]
     */
    private $instances = [];

    /**
     * Pivot data which links entity to parent.
     *
     * @var array
     */
    private $pivotData = [];

    /**
     * @param array        $data
     * @param string       $class
     * @param ORMInterface $orm
     */
    public function __construct(array $data, $class, ORMInterface $orm)
    {
        foreach ($data as $item) {
            $pivotData = $this->extractPivot($item);
            $this->instances[] = $instance = $orm->record($class, $item);

            if (!empty($pivotData)) {
                $this->pivotData[spl_object_hash($instance)] = $pivotData;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return count($this->instances);
    }

    /**
     * Get pivot data related to a given object if any.
     *
     * @param RecordInterface $record
     * @return array
     */
    public function pivotData(RecordInterface $record)
    {
        $objectHash = spl_object_hash($record);

        if (empty($this->pivotData[$objectHash])) {
            return [];
        }

        return $this->pivotData[$objectHash];
    }

    /**
     * Check if record or record with specified id presents in iteration.
     *
     * @param RecordInterface|string|int $lookup
     *
     * @return true
     */
    public function has($lookup)
    {
        foreach ($this->instances as $record) {
            if (
                is_array($lookup) && array_intersect_assoc($record->getFields(), $lookup) == $lookup
            ) {
                //Comparing via fields intersection
                return true;

            }

            if (
                is_scalar($lookup) && !empty($lookup) && $record->primaryKey() == $lookup
            ) {
                //Comparing using primary keys
                return true;
            }

            if (
                $record == $lookup || $record->getFields() == $lookup->getFields()
            ) {
                //Comparing as object vars
                return true;
            }
        }

        return false;
    }

    /**
     * Get all Records as array.
     *
     * @return RecordInterface[]
     */
    public function all()
    {
        return $this->instances;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->instances);
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return $this->instances;
    }

    /**
     * Flushing references.
     */
    public function __destruct()
    {
        $this->pivotData = [];
        $this->instances = [];
    }

    /**
     * @param array $data
     * @return array|null
     */
    private function extractPivot(array &$data)
    {
        if (!empty($data[ORMInterface::PIVOT_DATA])) {
            $pivotData = $data[ORMInterface::PIVOT_DATA];
            unset($data[ORMInterface::PIVOT_DATA]);

            return $pivotData;
        }

        return null;
    }
}