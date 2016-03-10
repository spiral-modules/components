<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Models;

/**
 * Simple initial interface for ORM and ODM Source classes. Only share one common method - findByPK.
 */
interface SourceInterface
{
    /**
     * Create new entity based on set of provided fields.
     *
     * @param array $fields
     *
     * @return EntityInterface
     */
    public function create($fields = []);

    /**
     * Find entity by primary key or return null.
     *
     * @param mixed $primaryKey
     *
     * @return IdentifiedInterface|null
     */
    public function findByPK($primaryKey);
}
