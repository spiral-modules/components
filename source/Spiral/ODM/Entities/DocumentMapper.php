<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\ODM\Entities;

class DocumentMapper
{
    /**
     * @param array $data
     * @return bool|\MongoId
     */
    public function insert(array $data)
    {
    }

    public function update(\MongoId $id, array $atomics)
    {
    }

    public function delete(\MongoId $id)
    {

    }
}