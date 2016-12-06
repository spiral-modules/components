<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\ODM\Entities\Schemas;

/**
 * Describes one ODM collection with it's primary class, indexes and etc.
 */
class CollectionSchema
{
    /**
     * Primary collection document.
     *
     * @var DocumentSchema
     */
    private $parent = null;

    /**
     * @param DocumentSchema $parent
     */
    public function __construct(DocumentSchema $parent)
    {
        $this->parent = $parent;
    }

    /**
     * Parent collection document.
     *
     * @return DocumentSchema
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Every document can be fetched from collection.
     *
     * @return DocumentSchema[]
     */
    public function getDocuments()
    {
        return array_merge([$this->parent], $this->parent->getChildren(true));
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->parent->getCollection();
    }

    /**
     * @return string
     */
    public function getDatabase()
    {
        return $this->parent->getDatabase();
    }

    /**
     * Requested collection indexes. May not be identical to existed collection indexes.
     *
     * @return array
     */
    public function getIndexes()
    {
        return $this->parent->getIndexes();
    }
}