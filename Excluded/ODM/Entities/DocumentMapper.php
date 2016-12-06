<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\ODM\Entities;

use Spiral\ODM\ODMInterface;

/**
 * Provides set of database related operations.
 */
class DocumentMapper
{
    /**
     * Related ODM model.
     *
     * @var string
     */
    private $class = '';

    /**
     * @var ODMInterface
     */
    protected $odm = null;

    /**
     * @param string       $class
     * @param ODMInterface $odm
     */
    public function __construct($class, ODMInterface $odm)
    {
        $this->class = $class;
        $this->odm = $odm;
    }

    /**
     * @return string
     */
    public function getCollection()
    {
        return $this->odm->schema($this->class, ODMInterface::D_DB);
    }

    /**
     * @return string
     */
    public function getDatabase()
    {
        return $this->odm->schema($this->class, ODMInterface::D_COLLECTION);
    }

    /**
     * Insert new document into mongo collection.
     *
     * @param array $data
     * @return bool|\MongoId|null Null if failure.
     */
    public function insert(array $data)
    {
        if ($this->mongoCollection()->insert($data)) {
            return $data['_id'];
        }

        return null;
    }

    /**
     * Update entity in related collection.
     *
     * @param \MongoId $id      Document id.
     * @param array    $updates Mongo updates in a form of new fields of atomic operations.
     * @return bool
     */
    public function update(\MongoId $id, array $updates)
    {
        return (bool)$this->mongoCollection()->update(['_id' => $id], $updates);
    }

    /**
     * Delete document from related collection.
     *
     * @param \MongoId $id
     * @return array|bool
     */
    public function delete(\MongoId $id)
    {
        return (bool)$this->mongoCollection()->remove([
            '_id' => $id
        ]);
    }

    /**
     * @return \MongoCollection
     */
    private function mongoCollection()
    {
        return $this->mongoDatabase()->selectCollection($this->getCollection());
    }

    /**
     * @return MongoDatabase
     */
    private function mongoDatabase()
    {
        return $this->odm->database($this->getDatabase());
    }
}