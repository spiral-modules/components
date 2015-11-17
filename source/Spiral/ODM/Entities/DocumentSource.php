<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\ODM\Entities;

use Spiral\Core\Traits\SaturateTrait;
use Spiral\ODM\DocumentEntity;
use Spiral\ODM\ODM;
use Spiral\ORM\Exceptions\SourceException;

/**
 * Source class associated to one or multiple (default implementation) ODM models.
 */
class DocumentSource extends Collection
{
    /**
     * Sugary!
     */
    use SaturateTrait;

    /**
     * Linked document model.
     */
    const DOCUMENT = null;

    /**
     * Associated document class. Attention, collection might return parent class on document
     * construction!
     *
     * @var string
     */
    private $class = null;

    /**
     * @param string $class
     * @param ODM    $odm
     * @param array  $query
     * @throws SourceException
     */
    public function __construct($class = null, ODM $odm = null, array $query = [])
    {
        if (empty($class)) {
            if (empty(static::DOCUMENT)) {
                throw new SourceException("Unable to create source without associate class.");
            }

            $class = static::DOCUMENT;
        }

        $this->class = $class;

        if (empty($odm)) {
            $odm = $this->saturate($odm, ODM::class);
        }

        //We can fetch collection and database from associated schema
        $schema = $odm->schema($this->class);
        parent::__construct($odm, $schema[ODM::D_DB], $schema[ODM::D_COLLECTION], $query);
    }

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