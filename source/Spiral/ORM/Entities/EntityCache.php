<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\ORM\Entities;

use Spiral\Models\IdentifiedInterface;
use Spiral\ORM\Exceptions\CacheException;

/**
 * Entity cache support. Used to share same model instance across it's child or related objects.
 */
class EntityCache
{
    /**
     * Indication that entity cache is enabled.
     *
     * @var bool
     */
    private $enabled = true;

    /**
     * Maximum entity cache size. Null is unlimited.
     *
     * @var int|null
     */
    private $cacheSize = null;

    /**
     * @var IdentifiedInterface[]
     */
    private $cache = [];

    /**
     * Check if entity cache enabled.
     *
     * @return bool
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /**
     * Enable or disable entity cache. Disabling cache will not flush it's values.
     *
     * @deprecated see configure
     *
     * @param bool     $enabled
     * @param int|null $maxSize Null = unlimited.
     *
     * @return $this
     */
    public function configureCache($enabled, $maxSize = null)
    {
        return $this->configure($enabled, $maxSize);
    }

    /**
     * Enable or disable entity cache. Disabling cache will not flush it's values.
     *
     * @param bool     $enabled
     * @param int|null $maxSize Null = unlimited.
     *
     * @return $this
     */
    public function configure($enabled, $maxSize = null)
    {
        $this->enabled = (bool)$enabled;
        if (!is_null($maxSize) && !is_int($maxSize)) {
            throw new \InvalidArgumentException('Cache size value has to be null or integer.');
        }

        $this->cacheSize = $maxSize;

        return $this;
    }

    /**
     * Add Record to entity cache (only if cache enabled). Primary key is required for caching.
     *
     * @param IdentifiedInterface $entity
     * @param bool                $ignoreLimit Cache overflow will be ignored.
     *
     * @return IdentifiedInterface
     *
     * @throws CacheException
     */
    public function remember(IdentifiedInterface $entity, $ignoreLimit = true)
    {
        if (empty($entity->primaryKey()) || !$this->enabled) {
            return $entity;
        }

        if (!$ignoreLimit && count($this->cache) > $this->cacheSize) {
            throw new CacheException('Entity cache size exceeded');
        }

        return $this->cache[get_class($entity) . '.' . $entity->primaryKey()] = $entity;
    }

    /**
     * Remove Record record from entity cache. Primary key is required for caching.
     *
     * @param IdentifiedInterface $entity
     */
    public function forget(IdentifiedInterface $entity)
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
     *
     * @return bool
     */
    public function has($class, $primaryKey)
    {
        return isset($this->cache[$class . '.' . $primaryKey]);
    }

    /**
     * Fetch entity from cache.
     *
     * @param string $class
     * @param mixed  $primaryKey
     *
     * @return null|IdentifiedInterface
     */
    public function get($class, $primaryKey)
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