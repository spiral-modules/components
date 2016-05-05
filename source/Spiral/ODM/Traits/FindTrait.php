<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\ODM\Traits;

use Spiral\ODM\Document;
use Spiral\ODM\Entities\DocumentSelector;
use Spiral\ODM\Entities\DocumentSource;
use Spiral\ODM\ODMInterface;
use Spiral\ORM\Exceptions\ORMException;
use Spiral\ORM\RecordEntity;

/**
 * Static record functionality including create and find methods.
 */
trait FindTrait
{
    /**
     * Find multiple documents based on provided query.
     *
     * Example:
     * User::find(['status' => 'active']);
     *
     * @param array $where Selection WHERE statement.
     *
     * @return DocumentSelector
     */
    public static function find($where = [])
    {
        return static::source()->find($where);
    }

    /**
     * Fetch one document based on provided query or return null.
     *
     * Example:
     * User::findOne(['name' => 'Wolfy-J'], ['_id' => -1]);
     *
     * @param array $where  Selection WHERE statement.
     * @param array $sortBy Sort by.
     *
     * @return RecordEntity|null
     */
    public static function findOne($where = [], array $sortBy = [])
    {
        return static::source()->findOne($where, $sortBy);
    }

    /**
     * Find document using it's primary key.
     *
     * Example:
     * User::findByID(1);
     *
     * @param mixed $primaryKey Primary key.
     *
     * @return Document|null
     */
    public static function findByPK($primaryKey)
    {
        return static::source()->findByPK($primaryKey);
    }

    /**
     * Instance of DocumentSource associated with specific document.
     *
     * @see Component::staticContainer()
     *
     * @param ODMInterface $odm ODM component, global container will be called if not instance
     *                          provided.
     *
     * @return DocumentSource
     *
     * @throws ORMException
     */
    public static function source(ODMInterface $odm = null)
    {
        /**
         * Using global container as fallback.
         *
         * @var ODMInterface $odm
         */
        if (empty($odm)) {
            //Using global container as fallback
            $odm = self::staticContainer()->get(ODMInterface::class);
        }

        return $odm->source(static::class);
    }
}
