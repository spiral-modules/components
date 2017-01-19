<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Entities\Relations\Traits;

use Spiral\ORM\ContextualCommandInterface;
use Spiral\ORM\RecordInterface;

/**
 * Looks for key values in command context AND in outer field if possible.
 */
trait LookupTrait
{
    /**
     * @param int                        $key
     * @param RecordInterface            $record
     * @param ContextualCommandInterface $command
     *
     * @return mixed|null
     */
    private function lookupKey(
        int $key,
        RecordInterface $record,
        ContextualCommandInterface $command
    ) {
        $key = $this->key($key);

        $context = $command->getContext();
        if (!empty($context[$key])) {
            //Key value found in a context
            return $context[$key];
        }

        if ($key == $this->primaryColumnOf($record)) {
            return $command->primaryKey();
        }

        //Fallback lookup in a record
        return $record->getField($key, null);
    }

    /**
     * Key name.
     *
     * @param int $key
     *
     * @return string
     */
    abstract protected function key(int $key): string;

    /**
     * Get primary key column
     *
     * @param RecordInterface $record
     *
     * @return string
     */
    abstract protected function primaryColumnOf(RecordInterface $record): string;
}