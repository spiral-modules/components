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
     * @var IdentifiedInterface[]
     */
    private $data = [];

    /**
     * Maximum entity cache size. Null is unlimited.
     *
     * @var int|null
     */
    private $maxSize = null;

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
     * Enable entity cache.
     *
     * @param int|null $maxSize
     */
    public function enable($maxSize = null)
    {
        $this->enabled = true;
        if (!is_null($maxSize) && !is_int($maxSize)) {
            throw new \InvalidArgumentException('Cache size value has to be null or integer.');
        }

        $this->maxSize = $maxSize;
    }

    /**
     * Disable entity cache without flushing it's data.
     */
    public function disable()
    {
        $this->enabled = false;
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

        if (!$ignoreLimit && count($this->data) > $this->maxSize) {
            throw new CacheException('Entity cache size exceeded');
        }

        return $this->data[get_class($entity) . '.' . $entity->primaryKey()] = $entity;
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

        unset($this->data[get_class($entity) . '.' . $entity->primaryKey()]);
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
        return isset($this->data[$class . '.' . $primaryKey]);
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
        if (empty($this->data[$class . '.' . $primaryKey])) {
            return null;
        }

        return $this->data[$class . '.' . $primaryKey];
    }

    /**
     * Flush content of entity cache.
     */
    public function flushCache()
    {
        $this->data = [];
    }

    /**
     * Destructing.
     */
    public function __destruct()
    {
        $this->flushCache();
    }
}