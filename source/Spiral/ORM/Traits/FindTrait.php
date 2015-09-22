<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */

namespace Spiral\ORM\Traits;

use Spiral\ORM\Entities\Selector;
use Spiral\ORM\Exceptions\ORMException;
use Spiral\ORM\ORM;
use Spiral\ORM\Record;

/**
 * Static find record functionality.
 */
trait FindTrait
{
    /**
     * Find multiple records based on provided query.
     *
     * Example:
     * User::find(['status' => 'active'], ['profile']);
     *
     * @param array|\Closure $where Selection WHERE statement.
     * @param array          $load  Array or relations to be pre-loaded.
     * @return Selector
     */
    public static function find($where = [], array $load = [])
    {
        return static::ormSelector()->load($load)->where($where);
    }

    /**
     * Fetch one record based on provided query or return null. Use second argument to specify
     * relations to be loaded.
     *
     * Example:
     * User::findOne(['name' => 'Wolfy-J'], ['profile'], ['id' => 'DESC']);
     *
     * @param array|\Closure $where   Selection WHERE statement.
     * @param array          $load    Array or relations to be pre-loaded.
     * @param array          $orderBy Sort by conditions.
     * @return Record|null
     */
    public static function findOne($where = [], array $load = [], array $orderBy = [])
    {
        $selector = static::find($where, $load);
        foreach ($orderBy as $column => $direction) {
            $selector->orderBy($column, $direction);
        }

        return $selector->findOne();
    }

    /**
     * Find record using it's primary key. Relation data can be preloaded with found record.
     *
     * Example:
     * User::findByID(1, ['profile']);
     *
     * @param mixed $primaryKey Primary key.
     * @param array $load       Array or relations to be pre-loaded.
     * @return Record|null
     */
    public static function findByPK($primaryKey, array $load = [])
    {
        return static::ormSelector()->load($load)->findByPK($primaryKey);
    }

    /**
     * Instance of ORM Selector associated with specific document.
     *
     * @see   Component::staticContainer()
     * @param ORM $orm ORM component, global container will be called if not instance provided.
     * @return Selector
     * @throws ORMException
     * @event selector(Selector $selector)
     */
    public static function ormSelector(ORM $orm = null)
    {
        //Ensure traits
        static::initialize();

        //Using global container as fallback
        $orm = self::saturate($orm, ORM::class);

        return static::events()->fire('selector', $orm->ormSelector(static::class));
    }
}