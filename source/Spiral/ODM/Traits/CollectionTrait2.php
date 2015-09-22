<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ODM\Traits;

use Spiral\ODM\Entities\Collection;
use Spiral\ODM\Exceptions\ODMException;
use Spiral\ODM\ODM;

/**
 * Static collection find functionality.
 */
trait CollectionTrait
{
    /**
     * Find multiple documents based on provided query. Selection might return parent documents.
     *
     * @param mixed $query Fields and conditions to filter by.
     * @return Collection
     * @throws ODMException
     */
    public static function find(array $query = [])
    {
        return static::odmCollection()->query($query);
    }

    /**
     * Find one document based on provided query and sorting.
     *
     * @param array $query     Fields and conditions to filter by.
     * @param array $sortBy    Sorting.
     * @param bool  $keepChain Only same class or child must be returned, parent document must be
     *                         ignored.
     * @return static|null
     * @throws ODMException
     */
    public static function findOne(array $query = [], array $sortBy = [], $keepChain = true)
    {
        $document = static::find($query)->sortBy($sortBy)->findOne();

        if ($keepChain && !$document instanceof static) {
            //Parent document found
            return null;
        }

        return $document;
    }

    /**
     * Find document using it's primary key.
     *
     * @param mixed $mongoID   Valid MongoId, string value must be automatically converted to
     *                         MongoId object.
     * @param bool  $keepChain Only same class or child must be returned, parent document must be
     *                         ignored.
     * @return static|null
     * @throws ODMException
     */
    public static function findByPK($mongoID, $keepChain = true)
    {
        if (!$mongoID = ODM::mongoID($mongoID)) {
            return null;
        }

        return static::findOne(['_id' => $mongoID], [], $keepChain);
    }

    /**
     * Instance of ODM Collection associated with specific document.
     *
     * @see   Component::staticContainer()
     * @param ODM $odm ODM component, global container will be called if not instance provided.
     * @return Collection
     * @throws ODMException
     * @event collection(Collection $collection)
     */
    public static function odmCollection(ODM $odm = null)
    {
        //Ensure traits
        static::initialize();

        //Using global container as fallback
        $odm = self::saturate($odm, ODM::class);

        return self::events()->fire('collection', $odm->odmCollection(static::class));
    }
}