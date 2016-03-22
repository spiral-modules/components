<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\ORM\Traits;

use Spiral\ORM\Entities\RecordSelector;
use Spiral\ORM\Entities\RecordSource;
use Spiral\ORM\Exceptions\ORMException;
use Spiral\ORM\ORM;
use Spiral\ORM\RecordEntity;

/**
 * Static record functionality including create and find methods.
 */
trait FindTrait
{
    /**
     * Find multiple records based on provided query.
     *
     * Example:
     * User::find(['status' => 'active'], ['profile']);
     *
     * @param array $where Selection WHERE statement.
     * @param array $load  Array or relations to be pre-loaded.
     *
     * @return RecordSelector
     */
    public static function find($where = [], array $load = [])
    {
        return static::source()->find($where)->load($load);
    }

    /**
     * Fetch one record based on provided query or return null. Use second argument to specify
     * relations to be loaded.
     *
     * Example:
     * User::findOne(['name' => 'Wolfy-J'], ['profile'], ['id' => 'DESC']);
     *
     * @param array $where   Selection WHERE statement.
     * @param array $load    Array or relations to be pre-loaded.
     * @param array $orderBy Sort by conditions.
     *
     * @return RecordEntity|null
     */
    public static function findOne($where = [], array $load = [], array $orderBy = [])
    {
        $source = static::find($where, $load);
        foreach ($orderBy as $column => $direction) {
            $source->orderBy($column, $direction);
        }

        return $source->findOne();
    }

    /**
     * Find record using it's primary key. Relation data can be preloaded with found record.
     *
     * Example:
     * User::findByID(1, ['profile']);
     *
     * @param mixed $primaryKey Primary key.
     * @param array $load       Array or relations to be pre-loaded.
     *
     * @return RecordEntity|null
     */
    public static function findByPK($primaryKey, array $load = [])
    {
        return static::source()->find()->load($load)->findByPK($primaryKey);
    }

    /**
     * Instance of RecordSource associated with specific record.
     *
     * @see Component::staticContainer()
     *
     * @param ORM $orm ORM component, global container will be called if not instance provided.
     *
     * @return RecordSource
     *
     * @throws ORMException
     */
    public static function source(ORM $orm = null)
    {
        /**
         * Using global container as fallback.
         *
         * @var ORM
         */
        if (empty($orm)) {
            //Using global container as fallback
            $orm = self::staticContainer()->get(ORM::class);
        }

        return $orm->source(static::class);
    }
}
