<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Components\Cache;

use Spiral\Component;
use Spiral\Core;


class CacheFacade extends Component
{
    /**
     * Some operations should be recorded.
     */
    use Traits\BenchmarkTrait;

    /**
     * Already constructed cache adapters.
     *
     * @var CacheStore[]
     */
    protected $stores = false;

    /**
     * Constructing CacheManager and selecting default adapter.
     *
     * @param ConfiguratorInterface $configurator
     */
    public function __construct(ConfiguratorInterface $configurator, HippocampusInterface $a);
{
$this->config = $configurator->getConfig($this);
}

/**
 * Adapter specified options.
 *
 * @param string $adapter
 * @return mixed
 */
public
function getStoreOptions($adapter)
{
    return $this->config['stores'][$adapter];
}

/**
 * Will return specified or default cache adapter. This function will load cache adapter if it
 * wasn't initiated, or fetch it from memory.
 *
 * @param string $store   Keep null, empty or not specified to get default cache adapter.
 * @param array  $options Custom store options to set or replace.
 * @return StoreInterface
 * @throws CacheException
 */
public
function store($store = null, array $options = [])
{
    $store = $store ?: $this->config['store'];

    if (isset($this->stores[$store]))
    {
        return $this->stores[$store];
    }

    if (!empty($options))
    {
        $this->config['stores'][$store] = $options;
    }

    $this->benchmark('cache::store', $store);

    $this->stores[$store] = self::getContainer()->get($this->config['stores'][$store]['class'], [
        'cache' => $this
    ], null, true);

    $this->benchmark('cache::store', $store);

    if ($store == $this->config['store'] && !$this->stores[$store]->isAvailable())
    {
        throw new CacheException(
            "Unable to use default store '{$store}', driver is unavailable."
        );
    }

    return $this->stores[$store];
}
}
