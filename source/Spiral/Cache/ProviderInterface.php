<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Cache;

use Spiral\Cache\Exceptions\CacheException;

/**
 * StoreInterface provider.
 */
interface ProviderInterface
{
    /**
     * Create specified or default cache adapter. This function will load cache adapter if it
     * was not initiated, or fetch it from memory.
     *
     * @param string $store   Keep null, empty or not specified to get default cache adapter.
     * @param array  $options Custom store options to set or replace.
     * @return StoreInterface
     * @throws CacheException
     */
    public function store($store = null, array $options = []);
}