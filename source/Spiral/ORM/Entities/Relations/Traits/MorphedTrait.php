<?php
/**
 * components
 *
 * @author    Wolfy-J
 */

namespace Spiral\ORM\Entities\Relations\Traits;

use Spiral\ORM\Exceptions\RelationException;
use Spiral\ORM\RecordInterface;

trait MorphedTrait
{
    /**
     * Resolve role for a given parent.
     *
     * @param \Spiral\ORM\RecordInterface $record
     *
     * @return string
     *
     * @throws \Spiral\ORM\Exceptions\RelationException
     */
    private function getRole(RecordInterface $record)
    {
        foreach ($this->getRoles() as $role => $class) {
            if (is_a($record, $class)) {
                return $role;
            }
        }

        throw new RelationException("Unable to find role for '{$record}'");
    }

    /**
     * Get all roles associated with given relation.
     *
     * @return array
     */
    abstract protected function getRoles(): array;
}