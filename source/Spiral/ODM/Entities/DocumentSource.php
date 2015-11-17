<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\ODM\Entities;

use Spiral\ODM\DocumentEntity;

class DocumentSource extends Collection
{
    /**
     * Create new Record based on set of provided fields.
     *
     * @final Change static method of entity, not this one.
     * @param array $fields
     * @return DocumentEntity
     */
    final public function create($fields = [])
    {
        //Letting entity to create itself (needed
        return call_user_func([$this->class, 'create'], $fields, $this->odm);
    }

    /**
     * Fetch one record from database using it's primary key. You can use INLOAD and JOIN_ONLY
     * loaders with HAS_MANY or MANY_TO_MANY relations with this method as no limit were used.
     *
     * @see findOne()
     * @param mixed $id Primary key value.
     * @return DocumentEntity|null
     */
    public function findByPK($id)
    {
        return $this->findOne(['_id' => $this->odm->mongoID($id)]);
    }

    /**
     * Select one document or it's fields from collection.
     *
     * @param array $query Fields and conditions to query by.
     * @return DocumentEntity|array
     */
    public function findOne(array $query = [])
    {
        return $this->createCursor($query, [], 1)->getNext();
    }
}