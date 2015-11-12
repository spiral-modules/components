<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Models;

/**
 * Simple initial interface for ORM and ODM Source classes.
 */
interface SourceInterface
{
    /**
     * Find entity by primary key or return null.
     *
     * @param mixed $primaryKey
     * @return IdentifiedInterface|null
     */
    public function findByPK($primaryKey);
}