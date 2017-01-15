<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Entities\Relations\Traits;

use Spiral\ORM\RecordInterface;

/**
 * Provides ability to compare entity and query.
 */
trait MatchTrait
{
    /**
     * Match entity by field intersection, instance values or primary key.
     *
     * @param RecordInterface             $record
     * @param RecordInterface|array|mixed $query
     *
     * @return bool
     */
    private function match(RecordInterface $record, $query): bool
    {
        if ($record === $query) {
            //Strict search
            return true;
        }

        if (is_scalar($query) && $record->primaryKey() == $query) {
            //Matched by PK
            return true;
        }

        $value = $record->packValue();

        if ($query instanceof RecordInterface) {
            if (!empty($query->primaryKey()) && $query->primaryKey() == $record->primaryKey()) {
                return true;
            }

            if ($value == $query->packValue()) {
                return true;
            }
        }

        //Comparasion over intersection
        if (is_array($query) && array_intersect_assoc($value, $query) == $query) {
            return true;
        }

        return false;
    }
}