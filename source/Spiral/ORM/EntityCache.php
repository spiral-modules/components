<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\ORM;

use Spiral\Core\Component;
use Spiral\Models\IdentifiedInterface;
use Spiral\ORM\Exceptions\CacheException;

/**
 * Entity cache support. Used to share same model instance across it's child or related objects.
 *
 * @todo Interface is needed
 */
class EntityCache extends Component
{
    /**
     * Indication that entity cache is enabled.
     *
     * @var bool
     */
    private $cacheEnabled = true;

    /**
     * Maximum entity cache size.
     *
     * @var int
     */
    private $cacheSize = 1000;

    /**
     * In cases when ORM cache is enabled every constructed instance will be stored here, cache used
     * mainly to ensure the same instance of object, even if was accessed from different spots.
     * Cache usage increases memory consumption and does not decreases amount of queries being made.
     *
     * @var RecordEntity[]
     */
    private $cache = [];

    /**
     * Check if entity cache enabled.
     *
     * @return bool
     */
    public function cacheEnabled()
    {
        return $this->cacheEnabled;
    }

    /**
     * Enable or disable entity cache. Disabling cache will not flush it's values.
     *
     * @param bool $enabled
     * @param int  $maxSize
     * @return $this
     */
    public function configureCache($enabled, $maxSize = null)
    {
        $this->cacheEnabled = (bool)$enabled;
        if (!empty($maxSize)) {
            $this->cacheSize = $maxSize;
        }

        return $this;
    }

    /**
     * Add Record to entity cache (only if cache enabled). Primary key is required for caching.
     *
     * @param IdentifiedInterface $entity
     * @param bool                $ignoreLimit Cache overflow will be ignored.
     * @return IdentifiedInterface
     * @throws CacheException
     */
    public function rememberEntity(IdentifiedInterface $entity, $ignoreLimit = true)
    {
        if (empty($entity->primaryKey()) || !$this->cacheEnabled) {
            return $entity;
        }

        if (!$ignoreLimit && count($this->cache) > $this->cacheSize) {
            throw new CacheException("Entity cache size exceeded.");
        }

        return $this->cache[get_class($entity) . '.' . $entity->primaryKey()] = $entity;
    }

    /**
     * Remove Record record from entity cache. Primary key is required for caching.
     *
     * @param IdentifiedInterface $entity
     */
    public function forgetEntity(IdentifiedInterface $entity)
    {
        if (empty($entity->primaryKey())) {
            return;
        }

        unset($this->cache[get_class($entity) . '.' . $entity->primaryKey()]);
    }

    /**
     * Check if desired entity was already cached.
     *
     * @param string $class
     * @param mixed  $primaryKey
     * @return bool
     */
    public function hasEntity($class, $primaryKey)
    {
        return isset($this->cache[$class . '.' . $primaryKey]);
    }

    /**
     * Fetch entity from cache.
     *
     * @param string $class
     * @param mixed  $primaryKey
     * @return null|IdentifiedInterface
     */
    public function getEntity($class, $primaryKey)
    {
        if (empty($this->cache[$class . '.' . $primaryKey])) {
            return null;
        }

        return $this->cache[$class . '.' . $primaryKey];
    }

    /**
     * Flush content of entity cache.
     */
    public function flushCache()
    {
        $this->cache = [];
    }

    /**
     * Destructing.
     */
    public function __destruct()
    {
        $this->flushCache();
    }
}