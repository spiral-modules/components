<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\ODM;

use Spiral\ODM\Entities\Collection;
use Spiral\ODM\Entities\DocumentSource;
use Spiral\ODM\Exceptions\ODMException;

/**
 * DocumentEntity with added ActiveRecord methods with direct find methods for collection.
 *
 * Document also provides an ability to specify aggregations using it's schema:
 *
 * protected $schema = [
 *     ...,
 *     'outer' => [self::ONE => Outer::class, [   //Reference to outer document using internal
 *          '_id' => 'self::outerID'              //outerID value
 *     ]],
 *     'many' => [self::MANY => Outer::class, [   //Reference to many outer document using
 *          'innerID' => 'self::_id'              //document primary key
 *     ]]
 * ];
 *
 * Note: self::{name} construction will be replaced with document value in resulted query, even
 * in case of arrays ;) You can also use dot notation to get value from nested document.
 *
 * @var array
 */
abstract class Document extends IsolatedDocument
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
        return static::source()->query($query);
    }

    /**
     * Find document using it's primary key.
     *
     * @param mixed $mongoID   Valid MongoId, string value must be automatically converted to
     *                         MongoId object.
     * @param bool  $keepChain Only same class or child must be returned, parent document must be
     *                         ignored.
     * @return Document|null
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
     * Find one document based on provided query and sorting.
     *
     * @param array $query     Fields and conditions to filter by.
     * @param array $sortBy    Sorting.
     * @param bool  $keepChain Only same class or child must be returned, parent document must be
     *                         ignored.
     * @return Document|null
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
     * Instance of ODM Collection associated with specific document.
     *
     * @see   Component::staticContainer()
     * @param ODM $odm ODM component, global container will be called if not instance provided.
     * @return DocumentSource|Collection
     * @throws ODMException
     */
    public static function source(ODM $odm = null)
    {
        if (empty($odm)) {
            //Using global container as fallback
            $odm = self::staticContainer()->get(ODM::class);
        }

        return $odm->source(static::class);
    }
}