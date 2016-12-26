<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\ODM\Traits;

use Interop\Container\ContainerInterface;
use Spiral\Core\Component;
use Spiral\Core\Exceptions\ScopeException;
use Spiral\ODM\CompositableInterface;
use Spiral\ODM\Document;
use Spiral\ODM\Entities\DocumentSelector;
use Spiral\ODM\Entities\DocumentSource;
use Spiral\ODM\ODM;

/**
 * Static record functionality including create and find methods.
 */
trait SourceTrait
{
    /**
     * Find multiple records based on provided query.
     *
     * Example:
     * User::find(['status' => 'active'], ['profile']);
     *
     * @param array $query Selection WHERE statement.
     *
     * @return DocumentSelector
     *
     * @throws ScopeException
     */
    public static function find(array $query = []): DocumentSelector
    {
        return static::source()->find($query);
    }

    /**
     * Fetch one record based on provided query or return null. Use second argument to specify
     * relations to be loaded.
     *
     * Example:
     * User::findOne(['name' => 'Wolfy-J'], ['profile'], ['id' => 'DESC']);
     *
     * @param array $where  Selection WHERE statement.
     * @param array $sortBy Sort by.
     *
     * @return CompositableInterface|Document|null
     *
     * @throws ScopeException
     */
    public static function findOne($where = [], array $sortBy = [])
    {
        return static::source()->findOne($where, $sortBy);
    }

    /**
     * Find record using it's primary key. Relation data can be preloaded with found record.
     *
     * Example:
     * User::findByID(1, ['profile']);
     *
     * @param mixed $primaryKey Primary key.
     *
     * @return CompositableInterface|Document|null
     *
     * @throws ScopeException
     */
    public static function findByPK($primaryKey)
    {
        return static::source()->findByPK($primaryKey);
    }

    /**
     * Instance of ORM Selector associated with specific document.
     *
     * @see   Component::staticContainer()
     **
     * @return DocumentSource
     *
     * @throws ScopeException
     */
    public static function source(): DocumentSource
    {
        /**
         * Container to be received via global scope.
         *
         * @var ContainerInterface $container
         */
        //Via global scope
        $container = self::staticContainer();

        if (empty($container)) //Via global scope
        {
            throw new ScopeException(sprintf(
                "Unable to get '%s' source, no container scope is available",
                static::class
            ));
        }

        return $container->get(ODM::class)->source(static::class);
    }

    /**
     * Trait can ONLY be added to components.
     *
     * @see Component
     *
     * @param ContainerInterface|null $container
     *
     * @return ContainerInterface|null
     */
    abstract protected static function staticContainer(ContainerInterface $container = null);
}